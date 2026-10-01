<?php

namespace App\Policies;

use App\Models\Gruppierung;
use App\Models\User;

/**
 * Pflege der Bezirke/Stämme zunächst zentral durch die Verwaltung
 * (F-MAP-09); ein eigener Bezirks-/Stamm-Scope folgt ggf. später.
 */
class GruppierungPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function view(User $user, Gruppierung $gruppierung): bool
    {
        return $user->isVerwaltung();
    }

    public function create(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function update(User $user, Gruppierung $gruppierung): bool
    {
        return $user->isVerwaltung();
    }

    public function delete(User $user, Gruppierung $gruppierung): bool
    {
        return $user->isVerwaltung();
    }
}
