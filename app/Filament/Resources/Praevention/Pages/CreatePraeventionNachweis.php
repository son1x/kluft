<?php

namespace App\Filament\Resources\Praevention\Pages;

use App\Filament\Resources\Praevention\PraeventionResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePraeventionNachweis extends CreateRecord
{
    protected static string $resource = PraeventionResource::class;

    protected static ?string $title = 'Nachweis eintragen';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'erfasst_von' => Filament::auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
