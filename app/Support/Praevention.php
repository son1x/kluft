<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Präventions-Nachweise (docs/praevention.md): sehen und pflegen dürfen nur
 * Vorstand und Verwaltung (Admins ebenso).
 */
class Praevention
{
    /** Erinnerungen so viele Tage vor Ablauf. */
    public const ERINNERUNG_TAGE = [90, 30];

    /** Abgelaufene Nachweise werden nach so vielen Tagen gelöscht. */
    public const LOESCHEN_NACH_TAGEN = 365;

    public static function zugriff(?User $user): bool
    {
        return Features::enabled(Features::PRAEVENTION) && $user !== null
            && ($user->isVerwaltung() || $user->hasRole('vorstand'));
    }

    /**
     * Wer die Übersicht der Erinnerungen bekommt: der Vorstand, sonst die Verwaltung.
     *
     * @return Collection<int, User>
     */
    public static function verantwortliche(): Collection
    {
        foreach (['vorstand', 'verwaltung'] as $rolle) {
            $users = User::query()->where('status', 'active')
                ->whereHas('roleAssignments', fn ($q) => $q->where('role', $rolle))->get();
            if ($users->isNotEmpty()) {
                return $users;
            }
        }

        return new Collection;
    }
}
