<?php

namespace App\Services;

use App\Jobs\CheckEventMembers;
use App\Mail\EventRegistrationMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\AuditLogger;
use App\Support\Teilnahmebeitraege;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ablauf der Veranstaltungsanmeldung (F-EV-12/13/15): Anmeldung als
 * "pending" → Bestätigungslink (48 h) → Platz oder Warteliste →
 * Stornierung per Link → Nachrücken von der Warteliste.
 */
class EventRegistrations
{
    /** Textversion bei Eintragung im Backend (statt Online-Einwilligung). */
    public const MANUELL = 'backend';

    public const CONFIRM_HOURS = 48;

    /**
     * @param  array{first_name: string, last_name: string, email: string, birth_year?: int|null, data?: array<string, mixed>, guardian_name?: string|null, guardian_consent?: bool, preis?: array{bezeichnung: string, cent: int}|null}  $input
     */
    public function register(Event $event, array $input): void
    {
        $email = mb_strtolower(trim($input['email']));

        // Doppelte Anmeldung derselben Person: keine zweite Zeile anlegen,
        // aber auch nicht verraten, dass es schon eine gibt.
        $exists = $event->registrations()
            ->whereIn('status', ['pending', 'confirmed', 'waitlist'])
            ->where('email', $email)
            ->whereRaw('lower(first_name) = ?', [mb_strtolower(trim($input['first_name']))])
            ->whereRaw('lower(last_name) = ?', [mb_strtolower(trim($input['last_name']))])
            ->exists();

        if ($exists) {
            return;
        }

        $token = Str::random(48);

        $registration = $event->registrations()->create([
            'status' => 'pending',
            'first_name' => trim($input['first_name']),
            'last_name' => trim($input['last_name']),
            'email' => $email,
            'birth_year' => $input['birth_year'] ?? null,
            'data' => array_filter($input['data'] ?? [], fn ($v) => filled($v)),
            'guardian_name' => $input['guardian_name'] ?? null,
            'guardian_consent' => (bool) ($input['guardian_consent'] ?? false),
            'preis_bezeichnung' => $input['preis']['bezeichnung'] ?? null,
            'preis_cent' => $input['preis']['cent'] ?? null,
            'consent_text_version' => $event->consentVersion(),
            'consent_at' => now(),
            'confirm_token_hash' => EventRegistration::hashToken($token),
            'purge_at' => now()->addHours(self::CONFIRM_HOURS),
        ]);

        $this->send($registration, 'doi', $token);
    }

    /**
     * Von der Veranstaltungsleitung im Backend eingetragen (einzeln oder als
     * Liste) – ohne Double-Opt-In. Status: „auto“ = bestätigt, solange Plätze
     * frei sind, danach Warteliste; „confirmed“ auch über die Platzzahl hinaus;
     * „waitlist“. Doppelte (gleicher Name und ggf. gleiche E-Mail) werden
     * übersprungen. Ergebnis je Person: confirmed, waitlist oder duplicate.
     *
     * @param  array{first_name: string, last_name: string, email?: string|null, birth_year?: int|null, data?: array<string, mixed>, preis?: array{bezeichnung: string, cent: int}|null}  $person
     * @return array{0: string, 1: EventRegistration|null}
     */
    public function addManually(Event $event, array $person, string $statusWahl, bool $notify, ?User $by = null): array
    {
        $email = mb_strtolower(trim((string) ($person['email'] ?? '')));
        $vorname = trim($person['first_name']);
        $nachname = trim($person['last_name']);
        $cancelToken = Str::random(48);

        [$result, $registration] = DB::transaction(function () use ($event, $person, $statusWahl, $email, $vorname, $nachname, $cancelToken) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());

            $doppelt = $event->registrations()
                ->whereIn('status', ['pending', 'confirmed', 'waitlist'])
                ->whereRaw('lower(first_name) = ?', [mb_strtolower($vorname)])
                ->whereRaw('lower(last_name) = ?', [mb_strtolower($nachname)])
                ->when($email !== '', fn ($q) => $q->whereIn('email', [$email, '']))
                ->exists();
            if ($doppelt) {
                return ['duplicate', null];
            }

            $status = match ($statusWahl) {
                'confirmed', 'waitlist' => $statusWahl,
                default => $event->isFull() ? 'waitlist' : 'confirmed',
            };

            $registration = $event->registrations()->create([
                'status' => $status,
                'first_name' => $vorname,
                'last_name' => $nachname,
                'email' => $email,
                'birth_year' => $person['birth_year'] ?? null,
                'data' => array_filter($person['data'] ?? [], fn ($v) => filled($v)),
                'preis_bezeichnung' => $person['preis']['bezeichnung'] ?? null,
                'preis_cent' => $person['preis']['cent'] ?? null,
                // Keine Online-Einwilligung: im Backend eingetragen (wer, steht im Audit-Log).
                'consent_text_version' => self::MANUELL,
                'consent_at' => now(),
                'confirmed_at' => now(),
                'cancel_token_hash' => $email !== '' ? EventRegistration::hashToken($cancelToken) : null,
                'purge_at' => $event->purgeAt(),
            ]);

            return [$status, $registration];
        });

        if ($registration) {
            AuditLogger::log('registration.manual', $by, EventRegistration::class, $registration->getKey(), request());
            if ($notify) {
                $this->send($registration, $result, $cancelToken);
            }
            Teilnahmebeitraege::fordern($registration, $notify);
        }

        return [$result, $registration];
    }

    /**
     * Double-Opt-In-Bestätigung. Ergebnis: confirmed, waitlist, full,
     * expired oder invalid.
     */
    public function confirm(string $token): array
    {
        $registration = EventRegistration::findByConfirmToken($token);

        if (! $registration) {
            return ['invalid', null];
        }

        if ($registration->status !== 'pending') {
            return [$registration->status === 'cancelled' ? 'invalid' : $registration->status, $registration];
        }

        if ($registration->created_at->addHours(self::CONFIRM_HOURS)->isPast()) {
            $registration->delete();

            return ['expired', null];
        }

        $cancelToken = Str::random(48);

        $result = DB::transaction(function () use ($registration, $cancelToken) {
            $event = Event::query()->lockForUpdate()->findOrFail($registration->event_id);

            $status = match (true) {
                ! $event->isFull() => 'confirmed',
                $event->waitlist_enabled => 'waitlist',
                default => 'full',
            };

            if ($status === 'full') {
                $registration->delete();

                return 'full';
            }

            $registration->update([
                'status' => $status,
                'confirmed_at' => now(),
                'confirm_token_hash' => null,
                'cancel_token_hash' => EventRegistration::hashToken($cancelToken),
                'purge_at' => $event->purgeAt(),
            ]);

            return $status;
        });

        if ($result === 'full') {
            return ['full', null];
        }

        $this->send($registration, $result, $cancelToken);
        app(PushNotifier::class)->registration($registration, $result);
        Teilnahmebeitraege::fordern($registration);

        // Automatisch nur mit technischem Konto; bei persönlicher NaMi-Anmeldung
        // prüft die Veranstaltungsleitung unter „Teilnehmende“.
        if ($registration->event->checksMembers() && ! NamiVerzeichnis::persoenlich()) {
            CheckEventMembers::dispatch($registration->event_id, false, [$registration->id]);
        }

        return [$result, $registration];
    }

    /**
     * Stornierung durch die Person selbst (Link) oder im Backend.
     */
    public function cancel(EventRegistration $registration): void
    {
        $wasConfirmed = $registration->status === 'confirmed';

        $registration->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_token_hash' => null,
        ]);

        $this->send($registration, 'cancelled');
        app(PushNotifier::class)->registration($registration, 'cancelled');
        Teilnahmebeitraege::stornieren($registration, 'Anmeldung storniert');

        if ($wasConfirmed) {
            $this->fillFromWaitlist($registration->event);
        }
    }

    /**
     * Rückt von der Warteliste nach, solange Plätze frei sind (F-EV-15).
     */
    public function fillFromWaitlist(Event $event): void
    {
        while (! $event->isFull()) {
            $next = $event->registrations()->where('status', 'waitlist')->orderBy('confirmed_at')->orderBy('id')->first();

            if (! $next) {
                return;
            }

            $this->promote($next);
        }
    }

    public function promote(EventRegistration $registration): void
    {
        $registration->update(['status' => 'confirmed']);
        $this->send($registration, 'promoted', $this->issueCancelToken($registration));
        Teilnahmebeitraege::fordern($registration);
    }

    public function moveToWaitlist(EventRegistration $registration): void
    {
        $registration->update(['status' => 'waitlist']);
        $this->send($registration, 'waitlist', $this->issueCancelToken($registration));
        Teilnahmebeitraege::stornieren($registration, 'Auf die Warteliste verschoben');
    }

    /**
     * Neuer Stornolink für eine Statusmail; der alte wird damit ungültig
     * (im Klartext ist kein Token gespeichert).
     */
    private function issueCancelToken(EventRegistration $registration): string
    {
        $token = Str::random(48);
        $registration->update(['cancel_token_hash' => EventRegistration::hashToken($token)]);

        return $token;
    }

    /**
     * Mailfehler dürfen den Ablauf nicht abbrechen; sie landen im Log.
     */
    private function send(EventRegistration $registration, string $kind, ?string $token = null): void
    {
        if (blank($registration->email)) {
            return; // von Hand eingetragen ohne E-Mail-Adresse
        }
        try {
            Mail::to($registration->email)->send(new EventRegistrationMail($registration, $kind, $token));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
