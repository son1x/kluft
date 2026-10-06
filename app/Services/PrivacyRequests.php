<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Person;
use App\Models\RegistrationDocument;
use App\Models\User;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Support\Features;
use App\Support\MailQueueEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Betroffenenrechte pro Person (Pflichtenheft 9.2): Auskunft (Export) und
 * Löschung über die E-Mail-Adresse. Durchsucht alles, was das CMS zu einer
 * Adresse speichert. Der Newsletter-Verteiler liegt nur bei Brevo,
 * Kontaktnachrichten werden nicht gespeichert.
 */
class PrivacyRequests
{
    public function __construct(private EventRegistrations $registrations) {}

    /**
     * @return array{registrations: Collection<int, EventRegistration>, people: Collection<int, Person>, users: Collection<int, User>, queued: int}
     */
    public function find(string $email): array
    {
        $email = mb_strtolower(trim($email));

        return [
            'registrations' => EventRegistration::query()->where('email', $email)->with(['event', 'documents'])->orderByDesc('id')->get(),
            'people' => Person::query()->whereRaw('LOWER(email) = ?', [$email])->get(),
            'users' => User::query()->whereRaw('LOWER(email) = ?', [$email])->with('roleAssignments')->get(),
            'queued' => $this->queueEntries($email)->count(),
        ];
    }

    /**
     * Auskunft als ZIP: auskunft.json mit allen Angaben (Zusatzangaben
     * entschlüsselt) und die hochgeladenen Dokumente.
     */
    public function export(string $email): string
    {
        $email = mb_strtolower(trim($email));
        $found = $this->find($email);

        $data = [
            'erstellt_am' => now()->toIso8601String(),
            'verantwortlich' => config('dpsg.site.name').', '.config('dpsg.contact.email'),
            'e_mail' => $email,
            'veranstaltungsanmeldungen' => $found['registrations']->map(fn (EventRegistration $r) => [
                'veranstaltung' => $r->event->title,
                'termin' => $r->event->dateLabel(),
                'status' => $r->statusLabel(),
                'vorname' => $r->first_name,
                'nachname' => $r->last_name,
                'geburtsjahr' => $r->birth_year,
                'angaben' => collect($r->event->formFields())->except('birth_year')
                    ->mapWithKeys(fn (array $field, string $key) => [$field['label'] => $r->event->fieldValueLabel($field, $r->data[$key] ?? null)])
                    ->filter(fn ($v) => $v !== null)->all(),
                'angemeldet_durch' => $r->guardian_name,
                'einwilligung' => $r->consent_at?->toIso8601String(),
                'einwilligungstext_version' => $r->consent_text_version,
                'mitgliederabgleich' => $r->member_status ? [
                    'ergebnis' => MitgliederAbgleich::STATUSES[$r->member_status] ?? $r->member_status,
                    'mitgliedsnummer' => $r->member_number,
                    'stamm' => $r->member_group,
                    'geprueft_am' => $r->member_checked_at?->toIso8601String(),
                ] : null,
                'bestaetigt_am' => $r->confirmed_at?->toIso8601String(),
                'storniert_am' => $r->cancelled_at?->toIso8601String(),
                'loeschung_geplant_am' => $r->purge_at?->toIso8601String(),
                'dokumente' => $r->documents->map(fn (RegistrationDocument $d) => 'dokumente/'.$d->id.'-'.$this->safe($d->downloadName()))->all(),
            ])->values()->all(),
            'personenvorstellung' => $found['people']->map(fn (Person $p) => [
                'name' => $p->name, 'funktion' => $p->role, 'gremium' => $p->gremium, 'stufe' => $p->stage,
                'vorstellungstext' => strip_tags((string) $p->bio), 'einwilligung' => $p->consent, 'aktiv' => $p->active,
                'foto' => $p->photo_media_id ? 'ja (Mediathek)' : 'nein',
            ])->values()->all(),
            'redaktionskonto' => $found['users']->map(fn (User $u) => [
                'name' => $u->name, 'status' => $u->status, 'letzte_anmeldung' => $u->last_login_at?->toIso8601String(),
                'rollen' => $u->roleAssignments->map(fn ($a) => trim($a->role.' '.$a->scope_type.' '.$a->scope_key))->all(),
                // Freiwillig gespeicherter NaMi-Zugang (Passwort wird nicht ausgegeben).
                'nami_zugang' => $u->hasNamiZugang() ? [
                    'mitgliedsnummer' => $u->nami_user,
                    'passwort' => 'verschlüsselt gespeichert (wird nicht ausgegeben)',
                    'zuletzt_geprueft' => $u->nami_checked_at?->toIso8601String(),
                ] : null,
            ])->values()->all(),
            'mails_in_warteschlange' => $found['queued'],
            'hinweise' => array_values(array_filter([
                Features::newsletter() ? config('dpsg.newsletter.name').' (Newsletter): Die Adressen liegen nur beim Versanddienst Brevo, nicht im CMS. Auskunft/Löschung dort.' : null,
                'Nachrichten aus dem Kontaktformular werden nur per Mail zugestellt und nicht im CMS gespeichert.',
            ])),
        ];

        $path = tempnam(sys_get_temp_dir(), 'auskunft').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('auskunft.json', (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach ($found['registrations'] as $registration) {
            foreach ($registration->documents as $document) {
                $disk = Storage::disk(RegistrationDocument::DISK);
                if ($disk->exists($document->path)) {
                    $zip->addFromString('dokumente/'.$document->id.'-'.$this->safe($document->downloadName()), (string) $disk->get($document->path));
                }
            }
        }
        $zip->close();

        return $path;
    }

    /**
     * Löscht Anmeldungen (samt Dokumenten), Personenvorstellungen und
     * wartende Mails zur Adresse. Redaktionskonten werden bewusst nicht
     * automatisch gelöscht (Rollen/Audit) – das geht unter „Benutzer“.
     * Frei werdende Plätze kommender Veranstaltungen rücken nach.
     *
     * @return array{registrations: int, people: int, queued: int}
     */
    public function delete(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $found = $this->find($email);

        $events = $found['registrations']->where('status', 'confirmed')->pluck('event')->unique('id');
        $found['registrations']->each->delete();
        $events->filter(fn (Event $event) => $event->starts_at->isFuture())
            ->each(fn (Event $event) => $this->registrations->fillFromWaitlist($event));

        $found['people']->each->delete();

        $queued = $this->queueEntries($email);
        DB::table('jobs')->whereIn('id', $queued->where('table', 'jobs')->pluck('id'))->delete();
        DB::table('failed_jobs')->whereIn('id', $queued->where('table', 'failed_jobs')->pluck('id'))->delete();

        return ['registrations' => $found['registrations']->count(), 'people' => $found['people']->count(), 'queued' => $queued->count()];
    }

    /**
     * Wartende und fehlgeschlagene Mails an die Adresse (Inhalt verschlüsselt,
     * daher Durchsicht in PHP).
     *
     * @return Collection<int, array{table: string, id: int}>
     */
    private function queueEntries(string $email): Collection
    {
        return collect(['jobs', 'failed_jobs'])->flatMap(fn (string $table) => DB::table($table)->get(['id', 'payload'])
            ->filter(fn (object $row) => mb_strtolower((string) MailQueueEntry::describe($row->payload)['recipient']) === $email)
            ->map(fn (object $row) => ['table' => $table, 'id' => (int) $row->id]))->values();
    }

    private function safe(string $name): string
    {
        return preg_replace('/[^\pL\pN ._–-]+/u', '-', $name) ?? 'dokument.pdf';
    }
}
