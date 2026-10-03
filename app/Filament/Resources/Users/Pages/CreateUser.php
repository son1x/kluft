<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Pages\Concerns\ActivatesOnFirstRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\DirectoryUnavailable;
use App\Services\EntraDirectory;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateUser extends CreateRecord
{
    use ActivatesOnFirstRole;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $oid = $data['directory_oid'] ?? null;
        unset($data['directory_oid']);
        $data['is_local_admin'] = false;

        if (blank($oid)) {
            // Verknüpfung bei der ersten Entra-Anmeldung über UPN/E-Mail (F-USR-03).
            $data['upn'] = $data['email'];

            return $data;
        }

        // Aus dem Verzeichnis gewählt (F-USR-02): Angaben serverseitig
        // noch einmal bei Microsoft abfragen, nicht dem Formular glauben.
        try {
            $person = app(EntraDirectory::class)->find((string) $oid);
        } catch (DirectoryUnavailable) {
            $person = null;
        }
        if ($person === null) {
            Notification::make()->danger()->title('Person im Verzeichnis nicht mehr abrufbar')
                ->body('Bitte neu suchen oder die Auswahl leeren und die Adresse von Hand eintragen.')->send();

            throw new Halt;
        }
        if (User::query()->where('entra_oid', $person['id'])->exists()) {
            Notification::make()->warning()->title('Diese Person hat schon ein Konto')
                ->body('Bitte in der Liste suchen und dort die Rolle ergänzen.')->send();

            throw new Halt;
        }

        return [
            ...$data,
            'entra_oid' => $person['id'],
            'name' => $person['name'],
            'email' => $person['email'],
            'upn' => $person['upn'],
        ];
    }

    protected function afterCreate(): void
    {
        $this->activateIfRoleAssigned();
    }
}
