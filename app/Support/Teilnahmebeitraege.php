<?php

namespace App\Support;

use App\Models\EventRegistration;
use App\Models\Zahlung;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Teilnahmebeiträge von Veranstaltungen über den Baustein „Zahlungen“
 * (docs/zahlungen.md): Forderung bei fester Anmeldung, Storno beim Abmelden.
 * Ohne Funktion „Zahlungen“ oder ohne Bankverbindung passiert nichts – der
 * Beitrag steht dann nur als Information an der Anmeldung.
 */
class Teilnahmebeitraege
{
    public static function aktiv(): bool
    {
        return Features::enabled(Features::ZAHLUNGEN) && Zahlungen::konto() !== null;
    }

    /**
     * Forderung anlegen (einmal je Anmeldung) und auf Wunsch die
     * Zahlungsaufforderung schicken. Fehler beim Mailen brechen nichts ab.
     */
    public static function fordern(EventRegistration $registration, bool $mail = true): ?Zahlung
    {
        $registration->loadMissing(['event', 'zahlungen']);
        if (! self::aktiv() || $registration->status !== 'confirmed' || (int) $registration->preis_cent < 1 || $registration->zahlung() !== null) {
            return null;
        }

        $event = $registration->event;
        $zahlung = Zahlungen::anlegen(
            $registration,
            (int) $registration->preis_cent,
            'VA',
            mb_substr($event->title.' ('.$event->dateLabel().')'.($registration->preis_bezeichnung ? ' – '.$registration->preis_bezeichnung : ''), 0, 140),
            $event->zahlungFaelligAm()->toDateString(),
        );
        $registration->unsetRelation('zahlungen');

        if ($mail && filled($registration->email)) {
            try {
                Zahlungen::auffordern($zahlung, $registration->email, $registration->guardian_name ?: $registration->fullName(),
                    'Teilnehmende Person: '.$registration->fullName().'. Die Anmeldung und den Zahlungsstand findest du auch unter „Meine Anmeldungen“: '.route('participants.index'));
            } catch (Throwable $e) {
                Log::warning('Zahlungsaufforderung für eine Anmeldung ging nicht raus', ['error' => class_basename($e)]);
            }
        }

        return $zahlung;
    }

    /** Offene Forderungen stornieren (Abmeldung, Warteliste). Bezahltes bleibt für die Erstattung. */
    public static function stornieren(EventRegistration $registration, string $grund): void
    {
        foreach ($registration->zahlungen()->where('status', 'offen')->get() as $zahlung) {
            $zahlung->stornieren(null, $grund);
        }
        $registration->unsetRelation('zahlungen');
    }
}
