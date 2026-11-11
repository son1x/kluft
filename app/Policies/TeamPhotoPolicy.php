<?php

namespace App\Policies;

use App\Models\TeamPhoto;
use App\Models\User;

/**
 * Personenvorstellung und Gruppenfotos: Verwaltung (5.4 „Globale Seiten“).
 */
class TeamPhotoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRedaktion();
    }

    public function view(User $user, TeamPhoto $record): bool
    {
        return $user->isRedaktion();
    }

    public function create(User $user): bool
    {
        return $user->isRedaktion();
    }

    public function update(User $user, TeamPhoto $record): bool
    {
        return $user->isRedaktion();
    }

    public function delete(User $user, TeamPhoto $record): bool
    {
        return $user->isRedaktion();
    }
}
