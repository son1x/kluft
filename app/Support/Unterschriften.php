<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\VorOrtUnterschrift;
use App\Services\TeilnehmendeListePdf;
use App\Services\Zuschuss\ZuschussPdf;
use Illuminate\Support\Facades\Storage;

/**
 * Digitale Unterschriften für die Teilnahmeliste (Zuschussantrag oder Schalter
 * „Unterschriftenliste“ der Veranstaltung, docs/vor-ort.md,
 * Stufe 2): Die teilnehmende Person unterschreibt einmal je Veranstaltung
 * auf dem Tablet. Mit „Teilnahmeliste festschreiben“ wird die Liste samt
 * Unterschriften als PDF abgelegt; die Einzelunterschriften werden danach
 * nach einer Frist (Standard 14 Tage) gelöscht.
 */
class Unterschriften
{
    /** Höchstgröße eines Unterschriftsbilds (PNG). */
    public const MAX_BYTES = 300_000;

    private const PREFIX = 'data:image/png;base64,';

    /** Unterschreiben ist möglich bei Zuschussantrag oder wenn die Veranstaltung die Unterschriftenliste eingeschaltet hat. */
    public static function moeglich(Event $event): bool
    {
        return $event->zuschussAktiv() || (bool) $event->unterschriftenliste;
    }

    public static function text(Event $event): string
    {
        return 'Ich bestätige meine Teilnahme an „'.$event->title.'“ ('.$event->dateLabel().').';
    }

    /** Tage zwischen Festschreiben und Löschen der Einzelunterschriften. */
    public static function loeschTage(): int
    {
        return max(1, (int) config('dpsg.unterschrift_loeschen_tage', 14));
    }

    /** Prüft das Bild; null = in Ordnung, sonst der Grund. */
    public static function pruefen(mixed $bild): ?string
    {
        if (! is_string($bild) || ! str_starts_with($bild, self::PREFIX)) {
            return 'Die Unterschrift fehlt oder hat ein falsches Format.';
        }
        $png = base64_decode(substr($bild, strlen(self::PREFIX)), true);
        if ($png === false || $png === '' || strlen($png) > self::MAX_BYTES) {
            return 'Die Unterschrift ist leer oder zu groß.';
        }
        $info = @getimagesizefromstring($png);
        if ($info === false || $info[2] !== IMAGETYPE_PNG || $info[0] < 20 || $info[1] < 10 || $info[0] > 4000 || $info[1] > 2000) {
            return 'Die Unterschrift ist kein gültiges Bild.';
        }

        return null;
    }

    /** Speichert (oder ersetzt) die Unterschrift; vorher pruefen(). */
    public static function speichern(EventRegistration $r, string $bild, ?User $user, mixed $zeit = null): VorOrtUnterschrift
    {
        $png = (string) base64_decode(substr($bild, strlen(self::PREFIX)), true);

        return VorOrtUnterschrift::query()->updateOrCreate(
            ['event_registration_id' => $r->getKey()],
            [
                'event_id' => $r->event_id,
                'bild' => $bild,
                'pruefsumme' => hash('sha256', $png),
                'user_id' => $user?->getKey(),
                'unterschrieben_am' => $zeit ?? now(),
                // Neu unterschrieben: erst mit dem nächsten Festschreiben wieder zum Löschen vormerken.
                'loeschen_am' => null,
            ],
        );
    }

    /**
     * Unterschriften für die Teilnahmeliste: Anmeldung => Data-URI.
     *
     * @return array<int, string>
     */
    public static function fuerListe(Event $event): array
    {
        return VorOrtUnterschrift::query()->where('event_id', $event->getKey())->get()
            ->filter(fn (VorOrtUnterschrift $u) => hash('sha256', (string) base64_decode(substr((string) $u->bild, strlen(self::PREFIX)), true)) === $u->pruefsumme)
            ->mapWithKeys(fn (VorOrtUnterschrift $u) => [$u->event_registration_id => (string) $u->bild])
            ->all();
    }

    public static function pfad(Event $event): string
    {
        return ($event->zuschussAktiv() ? 'zuschuss' : 'teilnahmelisten').'/teilnahmeliste-'.$event->getKey().'.pdf';
    }

    /**
     * Teilnahmeliste mit den jetzigen Unterschriften als PDF ablegen und die
     * Einzelunterschriften zum Löschen vormerken. Gibt die Zahl der
     * enthaltenen Unterschriften zurück.
     */
    public static function festschreiben(Event $event, ?User $user): int
    {
        $anzahl = count(self::fuerListe($event));
        $pdf = $event->zuschussAktiv()
            ? app(ZuschussPdf::class)->teilnahmeliste($event, festschreiben: true)
            : app(TeilnehmendeListePdf::class)->teilnahmeliste($event, festschreiben: true);
        Storage::disk('local')->put(self::pfad($event), $pdf);
        $event->forceFill(['teilnahmeliste_datei' => self::pfad($event), 'teilnahmeliste_am' => now()])->saveQuietly();

        VorOrtUnterschrift::query()->where('event_id', $event->getKey())
            ->update(['loeschen_am' => now()->addDays(self::loeschTage())]);
        AuditLogger::log("zuschuss.liste_festgeschrieben:{$anzahl}", $user, Event::class, $event->getKey(), request());

        return $anzahl;
    }

    /** Festgeschriebene Liste (PDF) oder null. */
    public static function festgeschrieben(Event $event): ?string
    {
        $pfad = $event->teilnahmeliste_datei;

        return filled($pfad) && Storage::disk('local')->exists($pfad) ? Storage::disk('local')->get($pfad) : null;
    }

    /** Einzelunterschriften, deren Frist nach dem Festschreiben abgelaufen ist, löschen. */
    public static function faelligeLoeschen(): int
    {
        return VorOrtUnterschrift::query()->whereNotNull('loeschen_am')->where('loeschen_am', '<=', now())->delete();
    }
}
