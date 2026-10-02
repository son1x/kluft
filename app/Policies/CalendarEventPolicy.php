<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

/**
 * Kalendertermine im Backend sieht, wer Veranstaltungen anlegen darf.
 * Bearbeitet werden die Termine nur in der Nextcloud.
 */
class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return app(EventPolicy::class)->create($user);
    }

    public function view(User $user, CalendarEvent $event): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return false;
    }
}
