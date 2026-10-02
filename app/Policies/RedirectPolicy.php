<?php

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;

/**
 * Weiterleitungen alter Adressen pflegt die Verwaltung (F-MIG-05).
 */
class RedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function view(User $user, Redirect $redirect): bool
    {
        return $user->isVerwaltung();
    }

    public function create(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function update(User $user, Redirect $redirect): bool
    {
        return $user->isVerwaltung();
    }

    public function delete(User $user, Redirect $redirect): bool
    {
        return $user->isVerwaltung();
    }
}
