<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Veranstaltungen, Pflichtenheft 5.4 und F-EV-08: Stufen-/Sektionsadmins
 * im eigenen Scope, Verwaltung global; der Vorstand sieht nur Anzahlen,
 * keine Teilnehmerdaten.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isVerwaltung()
            || $user->hasRole('vorstand')
            || $user->hasRole('stufenadmin')
            || $user->hasRole('sektionsadmin');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->hasRole('vorstand') || $this->inScope($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->isVerwaltung() || $user->hasRole('stufenadmin') || $user->hasRole('sektionsadmin');
    }

    public function update(User $user, Event $event): bool
    {
        return $this->inScope($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->inScope($user, $event);
    }

    /**
     * Teilnehmerdaten ansehen, bearbeiten und exportieren (need-to-know).
     */
    public function manageParticipants(User $user, Event $event): bool
    {
        return $this->inScope($user, $event);
    }

    private function inScope(User $user, Event $event): bool
    {
        if ($user->isVerwaltung()) {
            return true;
        }

        return ($event->stage && $user->hasRoleInScope('stufenadmin', 'stage', $event->stage))
            || ($event->section && $user->hasRoleInScope('sektionsadmin', 'section', $event->section));
    }
}
