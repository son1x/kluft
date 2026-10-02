<?php

namespace App\Policies;

use App\Models\ContactTopic;
use App\Models\User;

/**
 * Themen und Empfänger des Kontaktformulars pflegt die Verwaltung.
 */
class ContactTopicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function view(User $user, ContactTopic $topic): bool
    {
        return $user->isVerwaltung();
    }

    public function create(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function update(User $user, ContactTopic $topic): bool
    {
        return $user->isVerwaltung();
    }

    public function delete(User $user, ContactTopic $topic): bool
    {
        return $user->isVerwaltung();
    }
}
