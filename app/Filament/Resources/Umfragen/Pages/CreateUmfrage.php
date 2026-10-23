<?php

namespace App\Filament\Resources\Umfragen\Pages;

use App\Filament\Resources\Umfragen\UmfrageResource;
use App\Models\Umfrage;
use App\Support\AuditLogger;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUmfrage extends CreateRecord
{
    protected static string $resource = UmfrageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'erstellt_von' => Filament::auth()->id()];
    }

    protected function afterCreate(): void
    {
        AuditLogger::log('umfrage.angelegt', Filament::auth()->user(), Umfrage::class, $this->getRecord()->getKey(), request());
    }

    protected function getRedirectUrl(): string
    {
        return UmfrageResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
