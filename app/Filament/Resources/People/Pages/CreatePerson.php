<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use App\Support\AuditLogger;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePerson extends CreateRecord
{
    protected static string $resource = PersonResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PersonResource::storePortrait($data);
    }

    /** Konto zugeordnet oder geändert (Mein Eintrag auf der Website): ins Audit-Log. */
    protected function afterCreate(): void
    {
        $person = $this->record;
        if ($person instanceof Person && $person->user_id !== null) {
            AuditLogger::log('person.konto:'.($person->user_id ? 'zugeordnet' : 'entfernt'), Filament::auth()->user(), Person::class, $person->id, request());
        }
    }
}
