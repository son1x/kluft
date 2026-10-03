<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Pages\Concerns\ActivatesOnFirstRole;
use App\Filament\Resources\Users\Pages\Concerns\NotifiesAboutNewRoles;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use ActivatesOnFirstRole;
    use NotifiesAboutNewRoles;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('email', $data) && blank($this->record->entra_oid)) {
            $data['upn'] = $data['email'];
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $this->rememberRoles();
    }

    protected function afterSave(): void
    {
        $this->activateIfRoleAssigned();
        $this->notifyAboutNewRoles(newAccount: false);
    }
}
