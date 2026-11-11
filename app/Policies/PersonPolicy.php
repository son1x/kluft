<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

/**
 * Personenvorstellung und Gruppenfotos: Verwaltung (5.4 „Globale Seiten“).
 */
class PersonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->darf('website');
    }

    public function view(User $user, Person $record): bool
    {
        return $user->darf('website');
    }

    public function create(User $user): bool
    {
        return $user->darf('website');
    }

    public function update(User $user, Person $record): bool
    {
        return $user->darf('website');
    }

    public function delete(User $user, Person $record): bool
    {
        return $user->darf('website');
    }
}
