<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Support\RoleCatalog;

/**
 * Veranstaltungen, Pflichtenheft 5.4 und F-EV-08 (docs/rollen.md):
 * Verwaltung und Bildungsreferent*innen global; Stufen-/Sektionsadmins im
 * eigenen Bereich – je nach Website lesend (Veranstaltung und Teilnehmende
 * ansehen) oder auch schreibend (config dpsg.rechte.leitung_veranstaltungen);
 * die Rolle „Veranstaltungen bearbeiten – Bereich“ schreibt immer. Der
 * Vorstand sieht nur Anzahlen, keine Teilnehmerdaten.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRedaktion()
            || $user->hasRole('vorstand')
            || $user->hasRole('stufenadmin')
            || $user->hasRole('sektionsadmin')
            || $user->hasRole('veranstaltungen');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->hasRole('vorstand') || $this->lesen($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->isRedaktion()
            || $user->hasRole('veranstaltungen')
            || (! RoleCatalog::leitungLiestVeranstaltungen() && ($user->hasRole('stufenadmin') || $user->hasRole('sektionsadmin')));
    }

    public function update(User $user, Event $event): bool
    {
        return $this->schreiben($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->schreiben($user, $event);
    }

    /** Teilnehmende verwalten: bestätigen, eintragen, Nachrichten, Export. */
    public function manageParticipants(User $user, Event $event): bool
    {
        return $this->schreiben($user, $event);
    }

    /** Teilnehmende nur ansehen (Liste und Details). */
    public function viewParticipants(User $user, Event $event): bool
    {
        return $this->lesen($user, $event);
    }

    private function lesen(User $user, Event $event): bool
    {
        return $user->isRedaktion() || $this->leitung($user, $event) || $this->bereich($user, 'veranstaltungen', $event);
    }

    private function schreiben(User $user, Event $event): bool
    {
        return $user->isRedaktion()
            || $this->bereich($user, 'veranstaltungen', $event)
            || (! RoleCatalog::leitungLiestVeranstaltungen() && $this->leitung($user, $event));
    }

    private function leitung(User $user, Event $event): bool
    {
        return ($event->stage && $user->hasRoleInScope('stufenadmin', 'stage', $event->stage))
            || ($event->section && $user->hasRoleInScope('sektionsadmin', 'section', $event->section));
    }

    private function bereich(User $user, string $role, Event $event): bool
    {
        return ($event->stage && $user->hasRoleInScope($role, 'stage', $event->stage))
            || ($event->section && $user->hasRoleInScope($role, 'section', $event->section));
    }
}
