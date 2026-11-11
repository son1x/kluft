<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * S. Pflichtenheft 5.4: globale Seiten nur Verwaltung/Admin, Stufen-Seiten
 * zusätzlich vom zuständigen Stufenadmin, Vorstandsseiten (Seite des
 * Gremiums „leitung“ und Unterseiten) zusätzlich vom Vorstand. Anlegen und
 * Löschen bleiben bei Verwaltung bzw. Stufen-/Sektionsadmins.
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
        return $user->darf('seiten');
    }

    public function update(User $user, Page $page): bool
    {
        return $this->canManage($user, $page);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->darfUeberall('seiten');
    }

    private function canManage(User $user, Page $page): bool
    {
        if ($user->darfFuer('seiten', $page->stage, $page->section)) {
            return true;
        }

        return $user->darf('vorstandsseiten') && $page->istVorstandsseite();
    }
}
