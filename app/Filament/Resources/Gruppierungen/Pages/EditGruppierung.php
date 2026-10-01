<?php

namespace App\Filament\Resources\Gruppierungen\Pages;

use App\Filament\Resources\Gruppierungen\GruppierungResource;
use App\Filament\Resources\Gruppierungen\Pages\Concerns\TracksPosition;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGruppierung extends EditRecord
{
    use TracksPosition;

    protected static string $resource = GruppierungResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['typ'] ?? null) === 'bezirk') {
            $data['parent_id'] = null;
        }

        return $this->withPositionAuthor($data, $this->record->lat, $this->record->lng);
    }
}
