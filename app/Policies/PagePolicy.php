<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * S. Pflichtenheft 5.4: globale Seiten nur Verwaltung/Admin, Stufen-Seiten
 * zusätzlich vom zuständigen Stufenadmin.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roleAssignments->isNotEmpty();
    }

    public function view(User $user, Page $page): bool
    {
        return $this->canManage($user, $page);
    }

    public function create(User $user): bool
    {
        return $user->isVerwaltung() || $user->hasRole('stufenadmin') || $user->hasRole('sektionsadmin');
    }

    public function update(User $user, Page $page): bool
    {
        return $this->canManage($user, $page);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->isVerwaltung();
    }

    private function canManage(User $user, Page $page): bool
    {
        if ($user->isVerwaltung()) {
            return true;
        }

        if ($page->stage && $user->hasRoleInScope('stufenadmin', 'stage', $page->stage)) {
            return true;
        }

        if ($page->section && $user->hasRoleInScope('sektionsadmin', 'section', $page->section)) {
            return true;
        }

        return false;
    }
}
