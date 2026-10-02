<?php

namespace App\Policies;

use App\Models\GlossaryTerm;
use App\Models\User;

/**
 * Begriffserklärungen (Glossar) pflegt die Verwaltung.
 */
class GlossaryTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function view(User $user, GlossaryTerm $term): bool
    {
        return $user->isVerwaltung();
    }

    public function create(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function update(User $user, GlossaryTerm $term): bool
    {
        return $user->isVerwaltung();
    }

    public function delete(User $user, GlossaryTerm $term): bool
    {
        return $user->isVerwaltung();
    }
}
