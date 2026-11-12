<?php

namespace App\Policies;

use App\Models\StartseitenHinweis;
use App\Models\User;

/**
 * Startseiten-Hinweise pflegt, wer Website-Bausteine pflegen darf
 * (Verwaltung, Bildungsreferent*innen, Admins).
 */
class StartseitenHinweisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->darf('website');
    }

    public function view(User $user, StartseitenHinweis $hinweis): bool
    {
        return $user->darf('website');
    }

    public function create(User $user): bool
    {
        return $user->darf('website');
    }

    public function update(User $user, StartseitenHinweis $hinweis): bool
    {
        return $user->darf('website');
    }

    public function delete(User $user, StartseitenHinweis $hinweis): bool
    {
        return $user->darf('website');
    }
}
