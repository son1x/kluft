<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Pages\Concerns\ActivatesOnFirstRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use ActivatesOnFirstRole;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Verknüpfung bei der ersten Entra-Anmeldung über UPN/E-Mail (F-USR-03).
        $data['upn'] = $data['email'];
        $data['is_local_admin'] = false;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->activateIfRoleAssigned();
    }
}
