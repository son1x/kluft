<?php

namespace App\Filament\Resources\Gruppierungen\Pages;

use App\Filament\Resources\Gruppierungen\GruppierungResource;
use App\Filament\Resources\Gruppierungen\Pages\Concerns\TracksPosition;
use Filament\Resources\Pages\CreateRecord;

class CreateGruppierung extends CreateRecord
{
    use TracksPosition;

    protected static string $resource = GruppierungResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['typ'] ?? null) === 'bezirk') {
            $data['parent_id'] = null;
        }

        return $this->withPositionAuthor($data);
    }
}
