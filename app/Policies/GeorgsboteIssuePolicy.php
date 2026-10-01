<?php

namespace App\Policies;

use App\Models\GeorgsboteIssue;
use App\Models\User;

/**
 * "Georgsbote-Archiv pflegen": nur Verwaltung/Admin (Pflichtenheft 5.4).
 */
class GeorgsboteIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function view(User $user, GeorgsboteIssue $issue): bool
    {
        return $user->isVerwaltung();
    }

    public function create(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function update(User $user, GeorgsboteIssue $issue): bool
    {
        return $user->isVerwaltung();
    }

    public function delete(User $user, GeorgsboteIssue $issue): bool
    {
        return $user->isVerwaltung();
    }
}
