<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Veranstaltungen, Pflichtenheft 5.4 und F-EV-08 (docs/rollen.md), über die
 * Rechte-Matrix: „Veranstaltungen ansehen“ (samt sparsamer
 * Teilnehmendenliste), „Veranstaltungen bearbeiten“ (anlegen, Teilnehmende
 * verwalten) – je für einen Bereich oder die ganze Website – und „Alle
 * Veranstaltungen (ohne Teilnehmende)“ für den Vorstand.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->darf('veranstaltungen.lesen')
            || $user->darf('veranstaltungen.bearbeiten')
            || $user->darf('veranstaltungen.uebersicht');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->darf('veranstaltungen.uebersicht') || $this->lesen($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->darf('veranstaltungen.bearbeiten');
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

    /** Teilnehmende ansehen (wer nur liest: sparsame Liste). */
    public function viewParticipants(User $user, Event $event): bool
    {
        return $this->lesen($user, $event);
    }

    private function lesen(User $user, Event $event): bool
    {
        return $user->darfFuer('veranstaltungen.lesen', $event->stage, $event->section) || $this->schreiben($user, $event);
    }

    private function schreiben(User $user, Event $event): bool
    {
        return $user->darfFuer('veranstaltungen.bearbeiten', $event->stage, $event->section);
    }
}
