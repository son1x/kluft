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
}
