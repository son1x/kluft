<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Autoren-Workflow und Scope-Rechte, s. Pflichtenheft 5.4 (Berechtigungs-
 * matrix) und 6.1 F-CMS-10/11. Full-Access-Rollen (verwaltung/admin/
 * superadmin/vorstand) dürfen alles; stufenadmin nur innerhalb der
 * eigenen Stufe; autor nur eigene Beiträge.
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roleAssignments->isNotEmpty();
    }

    public function view(User $user, Post $post): bool
    {
        return $this->canManage($user, $post);
    }

    public function create(User $user): bool
    {
        return $user->roleAssignments->isNotEmpty();
    }

    public function update(User $user, Post $post): bool
    {
        return $this->canManage($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->canManage($user, $post);
    }

    /**
     * Ob der Nutzer den Beitrag direkt veröffentlichen darf (Status
     * "published" setzen) statt nur Entwurf/Zur Prüfung – Standard ist
     * Freigabe erforderlich (F-CMS-10).
     */
    public function publish(User $user): bool
    {
        if ($user->isVerwaltung()) {
            return true;
        }

        return $user->hasRole('stufenadmin')
            || $user->hasRole('sektionsadmin')
            || $user->hasRole('vorstand');
    }

    private function canManage(User $user, Post $post): bool
    {
        if ($user->isVerwaltung() || $user->hasRole('vorstand')) {
            return true;
        }

        if ($post->author_id === $user->id) {
            return true;
        }

        if ($post->stage && $user->hasRoleInScope('stufenadmin', 'stage', $post->stage)) {
            return true;
        }

        return false;
    }
}
