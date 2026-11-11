<?php

namespace App\Policies;

use App\Models\Gruppenstunde;
use App\Models\User;

/**
 * Gruppenstunden pflegt die Verwaltung; Stufenadmins die ihrer Stufe.
 */
class GruppenstundePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->darf('gruppenstunden');
    }

    public function view(User $user, Gruppenstunde $gruppe): bool
    {
        return $this->darfStufe($user, $gruppe->stage);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Gruppenstunde $gruppe): bool
    {
        return $this->darfStufe($user, $gruppe->stage);
    }

    public function delete(User $user, Gruppenstunde $gruppe): bool
    {
        return $this->darfStufe($user, $gruppe->stage);
    }

    private function darfStufe(User $user, string $stage): bool
    {
        return $user->darfUeberall('gruppenstunden') || in_array($stage, $user->bereicheFuer('gruppenstunden', 'stage'), true);
    }
}
