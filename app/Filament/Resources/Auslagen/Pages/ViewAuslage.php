<?php

namespace App\Filament\Resources\Auslagen\Pages;

use App\Filament\Resources\Auslagen\AuslageResource;
use App\Filament\Resources\Auslagen\Pages\Concerns\AuslageFormular;
use App\Models\Auslage;
use Filament\Resources\Pages\ViewRecord;

class ViewAuslage extends ViewRecord
{
    use AuslageFormular;

    protected static string $resource = AuslageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Auslage $record */
        $record = $this->record;

        return $this->zuFormular($record);
    }
}
