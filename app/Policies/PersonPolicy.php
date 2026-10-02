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
        return $user->isVerwaltung();
    }

    public function view(User $user, Person $record): bool
    {
        return $user->isVerwaltung();
    }

    public function create(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function update(User $user, Person $record): bool
    {
        return $user->isVerwaltung();
    }

    public function delete(User $user, Person $record): bool
    {
        return $user->isVerwaltung();
    }
}
