<?php

namespace App\Support;

use App\Mail\TeilnehmendeNachrichtMail;
use App\Models\Event;
use App\Models\EventNachricht;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Nachrichten an die Teilnehmenden einer Veranstaltung: Rundmail aus dem
 * Backend und Erinnerung vor Beginn (docs/plan-ehrenamt.md Nr. 1).
 */
class TeilnehmendeNachrichten
{
    /** Status, an die geschrieben werden kann. */
    public const STATUS = ['confirmed' => 'Bestätigt', 'waitlist' => 'Warteliste', 'pending' => 'Unbestätigt'];

    public const ERINNERUNG_STANDARD = "nur noch wenige Tage bis zur Veranstaltung – wir freuen uns auf dich!\n\nBitte denk an alles, was in der Beschreibung steht. Falls du doch nicht kommen kannst, storniere bitte über den Link aus deiner Bestätigungsmail, damit jemand von der Warteliste nachrücken kann.";

    /**
     * Anzahl der Personen mit E-Mail-Adresse in diesen Status.
     *
     * @param  list<string>  $status
     */
    public static function anzahl(Event $event, array $status): int
    {
        return self::empfaenger($event, $status)->count();
    }

    /**
     * @param  list<string>  $status
     * @return Collection<int, EventRegistration>
     */
    private static function empfaenger(Event $event, array $status)
    {
        $status = array_values(array_intersect($status, array_keys(self::STATUS)));

        return $event->registrations()->whereIn('status', $status)->where('email', '!=', '')->whereNotNull('email')->get()
            ->unique(fn (EventRegistration $r) => mb_strtolower($r->email).'|'.mb_strtolower($r->fullName()))->values();
    }

    /**
     * Rundmail verschicken (je Person eine Mail über die Warteschlange).
     *
     * @param  list<string>  $status
     */
    public static function senden(Event $event, array $status, string $betreff, string $text, ?User $von = null, string $art = 'nachricht'): EventNachricht
    {
        $empfaenger = self::empfaenger($event, $status);
        foreach ($empfaenger as $registration) {
            try {
                Mail::to($registration->email)->send(new TeilnehmendeNachrichtMail($registration, $betreff, $text));
            } catch (Throwable $e) {
                report($e);
            }
        }

        $nachricht = $event->nachrichten()->create([
            'art' => $art, 'betreff' => $betreff, 'text' => $text, 'an' => $status,
            'empfaenger' => $empfaenger->count(), 'user_id' => $von?->getKey(),
        ]);
        AuditLogger::log($art === 'erinnerung' ? 'event.erinnerung' : 'event.nachricht', $von, Event::class, $event->getKey());

        return $nachricht;
    }

    /**
     * Fällige Erinnerungen verschicken (täglicher Befehl): Veranstaltungen,
     * die in höchstens „erinnerung_tage“ Tagen beginnen und noch nicht erinnert sind.
     */
    public static function erinnerungenSenden(): int
    {
        $gesendet = 0;
        Event::query()->whereNotNull('erinnerung_tage')->whereNull('erinnerung_gesendet_am')
            ->where('status', '!=', 'cancelled')->where('starts_at', '>', now())
            ->get()
            ->filter(fn (Event $e) => $e->starts_at->copy()->startOfDay()->subDays((int) $e->erinnerung_tage)->lte(now()))
            ->each(function (Event $event) use (&$gesendet) {
                // Erst markieren: auch bei einem Fehler unterwegs nicht doppelt erinnern.
                $event->forceFill(['erinnerung_gesendet_am' => now()])->saveQuietly();
                self::senden($event, ['confirmed'], 'Erinnerung: '.$event->title.' am '.$event->starts_at->locale('de')->isoFormat('D. MMMM'),
                    filled($event->erinnerung_text) ? (string) $event->erinnerung_text : self::ERINNERUNG_STANDARD, null, 'erinnerung');
                $gesendet++;
            });

        return $gesendet;
    }
}
