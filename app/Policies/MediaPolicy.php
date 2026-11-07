<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

/**
 * Mediathek, s. Pflichtenheft 5.4 („Medien hochladen“): Vorstand,
 * Verwaltung und Admins voll; Autor, Stufen- und Sektionsadmin nur im
 * eigenen Scope. Medien haben keinen Scope, deshalb heißt das hier: alle
 * dürfen hochladen und auswählen, ändern und löschen nur ihre eigenen.
 */
class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roleAssignments->isNotEmpty();
    }

    public function view(User $user, Media $media): bool
    {
        return $user->roleAssignments->isNotEmpty();
    }

    public function create(User $user): bool
    {
        return $user->roleAssignments->isNotEmpty();
    }

    public function update(User $user, Media $media): bool
    {
        return $this->voll($user) || $this->eigene($user, $media);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->voll($user) || $this->eigene($user, $media);
    }

    /** Mehrere auf einmal löschen nur mit vollem Zugriff (die Auswahl kann fremde Dateien enthalten). */
    public function deleteAny(User $user): bool
    {
        return $this->voll($user);
    }

    private function voll(User $user): bool
    {
        return $user->isVerwaltung() || $user->hasRole('vorstand');
    }

    private function eigene(User $user, Media $media): bool
    {
        return $media->uploaded_by !== null && (int) $media->uploaded_by === (int) $user->id;
    }
}
