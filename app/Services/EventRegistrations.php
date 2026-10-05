<?php

namespace App\Services;

use App\Jobs\CheckEventMembers;
use App\Mail\EventRegistrationMail;
use App\Models\Event;
use App\Models\EventRegistration;
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
    public const CONFIRM_HOURS = 48;

    /**
     * @param  array{first_name: string, last_name: string, email: string, birth_year?: int|null, data?: array<string, mixed>, guardian_name?: string|null, guardian_consent?: bool}  $input
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
            'consent_text_version' => $event->consentVersion(),
            'consent_at' => now(),
            'confirm_token_hash' => EventRegistration::hashToken($token),
            'purge_at' => now()->addHours(self::CONFIRM_HOURS),
        ]);

        $this->send($registration, 'doi', $token);
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

        if ($registration->event->checksMembers()) {
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
    }

    public function moveToWaitlist(EventRegistration $registration): void
    {
        $registration->update(['status' => 'waitlist']);
        $this->send($registration, 'waitlist', $this->issueCancelToken($registration));
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
        try {
            Mail::to($registration->email)->send(new EventRegistrationMail($registration, $kind, $token));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
