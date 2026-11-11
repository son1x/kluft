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
        return $user->darf('website');
    }

    public function view(User $user, Download $download): bool
    {
        return $user->darf('website');
    }

    public function create(User $user): bool
    {
        return $user->darf('website');
    }

    public function update(User $user, Download $download): bool
    {
        return $user->darf('website');
    }

    public function delete(User $user, Download $download): bool
    {
        return $user->darf('website');
    }
}
