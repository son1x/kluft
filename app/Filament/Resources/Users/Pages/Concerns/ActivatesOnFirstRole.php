<?php

namespace App\Filament\Resources\Users\Pages\Concerns;

/**
 * Wer eine Rolle bekommt, ist damit freigeschaltet – auch wenn die
 * Freischaltung durch einen Stufenadmin erfolgt, der den Status selbst
 * nicht ändern darf (5.5).
 */
trait ActivatesOnFirstRole
{
    protected function activateIfRoleAssigned(): void
    {
        $user = $this->record->refresh();

        if ($user->status === 'pending' && $user->roleAssignments()->exists()) {
            $user->update(['status' => 'active']);
        }
    }
}
