<?php

namespace App\Support;

/**
 * Funktionen, die je Kluft-Instanz an- oder abgeschaltet sind
 * (config dpsg.features, dpsg.newsletter, dpsg.sections). Abgeschaltet
 * heißt: keine Routen, keine Menüpunkte, keine Backend-Bereiche.
 */
class Features
{
    public const GEORGSBOTE = 'georgsbote';

    public const ROADMAP = 'roadmap';

    public const RECHTSTRAEGER = 'rechtstraeger';

    public const WP_IMPORT = 'wp_import';

    public const DOWNLOADS = 'downloads';

    public static function enabled(string $feature): bool
    {
        return (bool) config('dpsg.features.'.$feature, false);
    }

    /** E-Mail-Newsletter über Brevo (beim DV „Georgsbrief“). */
    public static function newsletter(): bool
    {
        return filled(config('dpsg.newsletter.slug'));
    }

    /** Fachbereiche mit eigener Landingpage (z. B. /bolivien). */
    public static function sections(): bool
    {
        return ! empty(config('dpsg.sections'));
    }
}
