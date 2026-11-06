<?php

namespace App\Filament\Resources\Videokonferenzen\Pages;

use App\Filament\Resources\Videokonferenzen\VideokonferenzResource;
use App\Support\AuditLogger;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateVideokonferenz extends CreateRecord
{
    protected static string $resource = VideokonferenzResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Filament::auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        AuditLogger::log('videokonferenz.angelegt', Filament::auth()->user(), $this->record::class, $this->record->getKey(), request());
    }

    protected function getRedirectUrl(): string
    {
        return VideokonferenzResource::getUrl('view', ['record' => $this->record]);
    }
}
