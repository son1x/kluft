<?php

namespace App\Support;

use App\Models\User;
use App\Models\Videokonferenz;

/**
 * Rechte für Videokonferenzen (docs/videokonferenzen.md): anlegen wie bei
 * den Umfragen (ab Diözesanleitung); jede*r sieht die eigenen, Verwaltung
 * und Admins alle.
 */
class Videokonferenzen
{
    public static function zugriff(?User $user): bool
    {
        if (! Features::enabled(Features::VIDEOKONFERENZEN) || $user === null) {
            return false;
        }

        return $user->isRedaktion() || $user->hasRole('vorstand') || $user->hasRole('stufenadmin') || $user->hasRole('sektionsadmin');
    }

    public static function alleSehen(?User $user): bool
    {
        return $user !== null && $user->isRedaktion();
    }

    public static function darf(?User $user, Videokonferenz $k): bool
    {
        return self::zugriff($user) && (self::alleSehen($user) || $k->user_id === $user->id);
    }
}
