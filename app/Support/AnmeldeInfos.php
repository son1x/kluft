<?php

namespace App\Support;

use App\Mail\AnmeldungInfoMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Person;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Info-Mails an das Team, wenn sich jemand anmeldet (bestätigt oder
 * Warteliste) oder storniert. Je Veranstaltung zwei Schalter:
 * - Sammeladresse (Veranstaltungen → „Sammeladresse“, Standard die
 *   Kontaktadresse der Website, z. B. info@dpsg-trier.de);
 * - verantwortliche Person (Bildungsreferent*in, DL-Mitglied; ohne Angabe
 *   die Person, die die Veranstaltung angelegt hat).
 * Gleiche Adressen bekommen eine Mail.
 */
class AnmeldeInfos
{
    public const KEY = 'anmeldungen.sammeladresse';

    private const CACHE = 'anmeldungen:sammeladresse:v1';

    public static function sammeladresse(): ?string
    {
        $wert = Cache::rememberForever(self::CACHE, fn () => (string) SiteSetting::query()->whereKey(self::KEY)->value('value'));
        $adresse = $wert !== '' ? $wert : (string) config('dpsg.contact.email');

        return filter_var($adresse, FILTER_VALIDATE_EMAIL) ? mb_strtolower($adresse) : null;
    }

    public static function sammeladresseSetzen(?string $adresse, ?int $userId = null): void
    {
        $adresse = trim((string) $adresse);
        $adresse === '' || mb_strtolower($adresse) === mb_strtolower((string) config('dpsg.contact.email'))
            ? SiteSetting::query()->whereKey(self::KEY)->delete()
            : SiteSetting::query()->updateOrCreate(['key' => self::KEY], ['value' => mb_strtolower($adresse), 'updated_by' => $userId]);
        Cache::forget(self::CACHE);
    }

    /** Verantwortliche Adresse: eingetragen oder die der Person, die die Veranstaltung angelegt hat. */
    public static function verantwortlich(Event $event): ?string
    {
        $adresse = $event->verantwortlich_email ?: $event->creator?->email;

        return filter_var($adresse, FILTER_VALIDATE_EMAIL) ? mb_strtolower((string) $adresse) : null;
    }

    /**
     * Vorschläge für „verantwortlich“: aktive Benutzer*innen und Personen
     * aus Leitung und Büro mit Adresse.
     *
     * @return list<string>
     */
    public static function vorschlaege(): array
    {
        $personen = Person::query()->where('active', true)->get(['name', 'email'])->toBase()
            ->map(fn (Person $p) => $p->email ?: Person::suggestedEmail($p->name));
        $nutzer = User::query()->where('status', 'active')->pluck('email');

        return $personen->merge($nutzer)->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->map(fn ($e) => mb_strtolower((string) $e))->unique()->sort()->values()->all();
    }

    /** @return list<string> */
    public static function empfaenger(Event $event): array
    {
        return array_values(array_unique(array_filter([
            $event->info_sammeladresse ? self::sammeladresse() : null,
            $event->info_verantwortlich ? self::verantwortlich($event) : null,
        ])));
    }

    /** @param  'confirmed'|'waitlist'|'promoted'|'cancelled'  $art */
    public static function senden(EventRegistration $registration, string $art): void
    {
        $event = $registration->event;
        foreach (self::empfaenger($event) as $adresse) {
            Mail::to($adresse)->send(new AnmeldungInfoMail($registration, $art));
        }
    }
}
