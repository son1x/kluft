<?php

namespace App\Services;

use App\Models\AdressbuchKontakt;
use App\Models\Auslage;
use App\Models\ContactMessage;
use App\Models\DatenabgleichZeile;
use App\Models\Datenmeldung;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MaterialReservierung;
use App\Models\Person;
use App\Models\PraeventionNachweis;
use App\Models\RegistrationDocument;
use App\Models\Shop\Bestellung;
use App\Models\UmfrageAntwort;
use App\Models\UmfrageGewinnspiel;
use App\Models\User;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Umfragen\Auswertung;
use App\Support\Features;
use App\Support\MailQueueEntry;
use App\Support\SammelbestellungAblauf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     * @return array{registrations: Collection<int, EventRegistration>, people: Collection<int, Person>, users: Collection<int, User>, messages: Collection<int, ContactMessage>, bestellungen: Collection<int, Bestellung>, umfragen: Collection<int, UmfrageAntwort>, gewinnspiel: Collection<int, UmfrageGewinnspiel>, datenmeldungen: Collection<int, Datenmeldung>, adressbuch: Collection<int, AdressbuchKontakt>, praevention: Collection<int, PraeventionNachweis>, auslagen: Collection<int, Auslage>, material: Collection<int, MaterialReservierung>, queued: int}
     */
    public function find(string $email): array
    {
        $email = mb_strtolower(trim($email));

        return [
            'registrations' => EventRegistration::query()->where('email', $email)->with(['event', 'documents'])->orderByDesc('id')->get(),
            'people' => Person::query()->whereRaw('LOWER(email) = ?', [$email])->get(),
            'users' => User::query()->whereRaw('LOWER(email) = ?', [$email])->with('roleAssignments')->get(),
            'messages' => ContactMessage::query()->where('email', $email)->orderByDesc('id')->get(),
            'bestellungen' => Schema::hasTable('shop_bestellungen')
                ? Bestellung::query()->whereRaw('LOWER(email) = ?', [$email])->whereNull('anonymisiert_am')->with('runde', 'positionen', 'zahlungen')->orderByDesc('id')->get()
                : new EloquentCollection,
            // Nur nicht anonyme Umfragen haben Namen/Mail; das Gewinnspiel liegt getrennt.
            'umfragen' => Schema::hasTable('umfrage_antworten')
                ? UmfrageAntwort::query()->whereRaw('LOWER(email) = ?', [$email])->with('umfrage')->orderByDesc('id')->get()
                : new EloquentCollection,
            'gewinnspiel' => Schema::hasTable('umfrage_gewinnspiel')
                ? UmfrageGewinnspiel::query()->where('email', $email)->with('umfrage')->orderByDesc('id')->get()
                : new EloquentCollection,
            // NaMi-Datenabgleich: Mail des Mitglieds oder der Erziehungsberechtigten.
            'datenmeldungen' => Schema::hasTable('datenmeldungen')
                ? Datenmeldung::query()->where('email_hash', Datenmeldung::hash($email))->orWhere('email_eltern_hash', Datenmeldung::hash($email))->orderByDesc('id')->get()
                : new EloquentCollection,
            // Adressbuch (Kontakte, auch Externe), samt Verteilern.
            'adressbuch' => Schema::hasTable('adressbuch_kontakte')
                ? AdressbuchKontakt::query()->where('email', $email)->with('verteiler')->get()
                : new EloquentCollection,
            // Präventions-Nachweise (nur Daten), docs/praevention.md.
            'praevention' => Schema::hasTable('praevention_nachweise')
                ? PraeventionNachweis::query()->where('email', $email)->orderBy('gueltig_bis')->get()
                : new EloquentCollection,
            // Auslagen: nur Auskunft, Löschung erst nach der Aufbewahrungsfrist.
            'auslagen' => Schema::hasTable('auslagen')
                ? Auslage::query()->where('email', $email)->orderByDesc('id')->get()
                : new EloquentCollection,
            // Materialverleih: Reservierungen und Anfragen.
            'material' => Schema::hasTable('material_reservierungen')
                ? MaterialReservierung::query()->where('email', $email)->with('material')->orderByDesc('von')->get()
                : new EloquentCollection,
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
                    'eigene_gruppierung' => $r->member_own_group,
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
            'nachrichten' => $found['messages']->map(fn (ContactMessage $m) => [
                'eingang' => $m->created_at->toIso8601String(), 'thema' => $m->thema, 'name' => $m->name,
                'betreff' => $m->betreff, 'nachricht' => $m->nachricht, 'zugestellt_an' => $m->empfaenger,
                'status' => ContactMessage::STATUSES[$m->status] ?? $m->status,
                'loeschung_spaetestens' => $m->created_at->copy()->addMonths(ContactMessage::aufbewahrungMonate())->toDateString(),
            ])->values()->all(),
            'sammelbestellungen' => $found['bestellungen']->map(fn (Bestellung $b) => [
                'runde' => $b->runde->name, 'eingang' => $b->created_at->toIso8601String(), 'name' => $b->name, 'telefon' => $b->telefon,
                'rolle' => Bestellung::ROLLEN[$b->rolle] ?? $b->rolle, 'abholung' => $b->abholung, 'bemerkung' => $b->bemerkung,
                'stand' => Bestellung::STAENDE[$b->stand()] ?? $b->stand(),
                'mitgliedschaft_geprueft' => $b->mitgliedschaft ? (Bestellung::MITGLIEDSCHAFT[$b->mitgliedschaft] ?? $b->mitgliedschaft) : null,
                'positionen' => $b->positionen->map(fn ($p) => ['fuer' => $p->mitglied, 'stufe' => $p->stufe, 'artikel' => $p->bezeichnung, 'groesse' => $p->groesse,
                    'anzahl' => $p->menge, 'preis_cent' => $p->preis_cent, 'ausgegeben_am' => $p->ausgegeben_am?->toDateString()])->all(),
                'zahlungen' => $b->zahlungen->map(fn ($z) => ['verwendungszweck' => $z->referenz, 'betrag_cent' => $z->betrag_cent, 'status' => $z->status])->all(),
            ])->values()->all(),
            'umfragen' => $found['umfragen']->map(fn (UmfrageAntwort $a) => [
                'umfrage' => $a->umfrage?->titel, 'datum' => $a->eingegangen_am->toDateString(), 'name' => $a->name,
                'antworten' => $a->umfrage ? $a->umfrage->fragen->filter->istAntwortfrage()->mapWithKeys(fn ($f) => [$f->text => [
                    'antwort' => Auswertung::wertText($a->daten[$f->schluessel]['wert'] ?? null),
                    'kommentar' => $a->daten[$f->schluessel]['kommentar'] ?? null,
                ]])->all() : [],
            ])->values()->all(),
            'gewinnspiele' => $found['gewinnspiel']->map(fn (UmfrageGewinnspiel $g) => [
                'umfrage' => $g->umfrage?->titel, 'name' => $g->name, 'datum' => $g->eingegangen_am->toDateString(), 'gewonnen' => $g->gewonnen,
            ])->values()->all(),
            'datenmeldungen' => $found['datenmeldungen']->map(fn (Datenmeldung $m) => [
                'eingang' => $m->created_at->toIso8601String(), 'bestaetigt_am' => $m->bestaetigt_am?->toIso8601String(),
                'angaben' => (array) $m->daten,
                'loeschung_spaetestens' => ($m->bestaetigt_am ?? $m->created_at)->copy()->addMonths(6)->toDateString(),
            ])->values()->all(),
            'adressbuch' => $found['adressbuch']->map(fn (AdressbuchKontakt $k) => [
                'name' => $k->name, 'email' => $k->email, 'organisation' => $k->organisation, 'notiz' => $k->notiz,
                'verteiler' => $k->verteiler->pluck('name')->all(), 'angelegt' => $k->created_at?->toDateString(),
            ])->values()->all(),
            'praevention' => $found['praevention']->map(fn (PraeventionNachweis $n) => [
                'name' => $n->name, 'art' => $n->artLabel(), 'bezeichnung' => $n->bezeichnung,
                'datum' => $n->datum?->toDateString(), 'gueltig_bis' => $n->gueltig_bis->toDateString(),
                'loeschung' => $n->gueltig_bis->copy()->addYear()->toDateString(),
            ])->values()->all(),
            'auslagen' => $found['auslagen']->map(fn (Auslage $a) => [
                'anlass' => $a->anlass, 'datum' => $a->datum->toDateString(), 'art' => $a->beschreibung(),
                'betrag' => $a->summe_cent / 100, 'status' => Auslage::STATUS[$a->status] ?? $a->status,
                'belege' => count($a->belege ?? []), 'ausgezahlt_am' => $a->ausgezahlt_am?->toDateString(),
                'bankverbindung_gespeichert' => $a->konto !== null,
            ])->values()->all(),
            'materialverleih' => $found['material']->map(fn (MaterialReservierung $r) => [
                'material' => $r->material->name, 'von' => $r->von->toDateString(), 'bis' => $r->bis->toDateString(),
                'name' => $r->name, 'telefon' => $r->telefon, 'gruppierung' => $r->gruppierung, 'zweck' => $r->zweck,
                'status' => MaterialReservierung::STATUS[$r->status] ?? $r->status,
            ])->values()->all(),
            'mails_in_warteschlange' => $found['queued'],
            'hinweise' => array_values(array_filter([
                Features::newsletter() ? config('dpsg.newsletter.name').' (Newsletter): Die Adressen liegen nur beim Versanddienst Brevo, nicht im CMS. Auskunft/Löschung dort.' : null,
                'Nachrichten aus dem Kontaktformular gehen per Mail an die zuständigen Personen; Kopien in deren Postfächern sind hier nicht erfasst.'
                    .(Features::enabled(Features::NACHRICHTEN) || $found['messages']->isNotEmpty() ? ' Im Nachrichten-Eingang gespeicherte Nachrichten stehen oben unter „nachrichten“.' : ' Im CMS werden sie nicht gespeichert.'),
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
     * @return array{registrations: int, people: int, messages: int, bestellungen: int, umfragen: int, datenmeldungen: int, adressbuch: int, praevention: int, material: int, queued: int}
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
        $found['messages']->each->delete();
        // Sammelbestellungen: Namen und Adressen weg, Summen und Zahlungen bleiben für die Kasse.
        $found['bestellungen']->each(fn (Bestellung $b) => SammelbestellungAblauf::anonymisieren($b));
        $found['umfragen']->each->delete();
        $found['gewinnspiel']->each->delete();
        // NaMi-Datenabgleich: Meldungen samt ihren Zeilen der Unterschiedsliste.
        if ($found['datenmeldungen']->isNotEmpty()) {
            DatenabgleichZeile::query()->where('quelle', 'meldung')->whereIn('quelle_id', $found['datenmeldungen']->pluck('id'))->delete();
            $found['datenmeldungen']->each->delete();
        }

        // Adressbuch: Kontakt samt Zugehörigkeit zu Verteilern.
        $found['adressbuch']->each->delete();
        $found['praevention']->each->delete();
        $found['material']->each->delete();

        $queued = $this->queueEntries($email);
        DB::table('jobs')->whereIn('id', $queued->where('table', 'jobs')->pluck('id'))->delete();
        DB::table('failed_jobs')->whereIn('id', $queued->where('table', 'failed_jobs')->pluck('id'))->delete();

        return ['registrations' => $found['registrations']->count(), 'people' => $found['people']->count(), 'messages' => $found['messages']->count(),
            'bestellungen' => $found['bestellungen']->count(), 'umfragen' => $found['umfragen']->count() + $found['gewinnspiel']->count(), 'datenmeldungen' => $found['datenmeldungen']->count(), 'adressbuch' => $found['adressbuch']->count(), 'praevention' => $found['praevention']->count(), 'material' => $found['material']->count(), 'queued' => $queued->count()];
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
