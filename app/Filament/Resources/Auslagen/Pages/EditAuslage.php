<?php

namespace App\Filament\Resources\Auslagen\Pages;

use App\Filament\Resources\Auslagen\AuslageResource;
use App\Filament\Resources\Auslagen\Pages\Concerns\AuslageFormular;
use App\Models\Auslage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAuslage extends EditRecord
{
    use AuslageFormular;

    protected static string $resource = AuslageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Auslage $record */
        $record = $this->record;

        return $this->zuFormular($record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Auslage $record */
        $record = $this->record;

        return $this->zuSpalten($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->label('Zurückziehen')->modalHeading('Auslage zurückziehen?')];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
