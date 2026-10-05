<?php

namespace App\Policies;

use App\Models\Download;
use App\Models\User;

/**
 * Download-Seite (Methoden, Formulare, Arbeitshilfen) pflegt die Verwaltung.
 */
class DownloadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function view(User $user, Download $download): bool
    {
        return $user->isVerwaltung();
    }

    public function create(User $user): bool
    {
        return $user->isVerwaltung();
    }

    public function update(User $user, Download $download): bool
    {
        return $user->isVerwaltung();
    }

    public function delete(User $user, Download $download): bool
    {
        return $user->isVerwaltung();
    }
}
