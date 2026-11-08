<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use App\Support\AuditLogger;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditPerson extends EditRecord
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PersonResource::storePortrait($data);
    }

    /** Konto zugeordnet oder geändert (Mein Eintrag auf der Website): ins Audit-Log. */
    protected function afterSave(): void
    {
        $person = $this->record;
        if ($person instanceof Person && $person->wasChanged('user_id')) {
            AuditLogger::log('person.konto:'.($person->user_id ? 'zugeordnet' : 'entfernt'), Filament::auth()->user(), Person::class, $person->id, request());
        }
    }
}
