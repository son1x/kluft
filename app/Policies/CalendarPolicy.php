<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\User;

/**
 * Kalenderquellen sind eine Integration (ICS-Freigabelinks wirken wie
 * Zugangsdaten): laut Berechtigungsmatrix 5.4 nur Admin.
 */
class CalendarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Calendar $calendar): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Calendar $calendar): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Calendar $calendar): bool
    {
        return $user->isAdmin();
    }
}
