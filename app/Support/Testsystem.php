<?php

namespace App\Support;

/**
 * Kennzeichnung eines Testsystems (docs/livegang.md), gesteuert über
 * KLUFT_TESTSYSTEM im GitHub-Environment:
 * - leer: normale Website
 * - offen: Band „Testsystem“ auf jeder Seite, Suchmaschinen ausgesperrt
 * - geschlossen: zusätzlich nur nach Anmeldung im Backend sichtbar
 */
class Testsystem
{
    public static function modus(): ?string
    {
        $modus = strtolower(trim((string) config('kluft.testsystem')));

        return in_array($modus, ['offen', 'geschlossen'], true) ? $modus : null;
    }

    public static function aktiv(): bool
    {
        return self::modus() !== null;
    }

    public static function geschlossen(): bool
    {
        return self::modus() === 'geschlossen';
    }

    public static function liveUrl(): ?string
    {
        $url = trim((string) config('kluft.live_url'));

        return str_starts_with($url, 'https://') ? rtrim($url, '/') : null;
    }

    /**
     * Wohin das Logo im schlichten Rahmen (vorab freigegebene Veranstaltung)
     * führt: die Live-Adresse, vor dem Livegang die alte Website aus
     * dpsg.einbetten_von.
     */
    public static function zurueckUrl(): ?string
    {
        $url = self::liveUrl() ?? (string) (((array) config('dpsg.einbetten_von', []))[0] ?? '');

        return str_starts_with($url, 'https://') ? rtrim($url, '/') : null;
    }
}
