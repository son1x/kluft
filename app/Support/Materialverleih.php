<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Materialverleih (docs/materialverleih.md): pflegen dürfen Materialwart*innen
 * (Rolle „material“), Verwaltung und Admins.
 */
class Materialverleih
{
    /** Reservierungen werden so viele Monate nach der Rückgabe gelöscht. */
    public const LOESCHEN_MONATE = 6;

    public static function aktiv(): bool
    {
        return Features::enabled(Features::MATERIALVERLEIH);
    }

    public static function oeffentlich(): bool
    {
        return self::aktiv() && Features::enabled(Features::MATERIALVERLEIH_OEFFENTLICH);
    }

    public static function zugriff(?User $user): bool
    {
        return self::aktiv() && $user !== null && ($user->hasRole('material') || $user->isVerwaltung());
    }

    /**
     * Wer Anfragen per Mail bekommt: Materialwart*innen, sonst die Verwaltung.
     *
     * @return Collection<int, User>
     */
    public static function empfaenger(): Collection
    {
        foreach (['material', 'verwaltung'] as $rolle) {
            $users = User::query()->where('status', 'active')
                ->whereHas('roleAssignments', fn ($q) => $q->where('role', $rolle))->get();
            if ($users->isNotEmpty()) {
                return $users;
            }
        }

        return collect();
    }
}
