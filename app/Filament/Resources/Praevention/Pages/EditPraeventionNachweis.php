<?php

namespace App\Filament\Resources\Praevention\Pages;

use App\Filament\Resources\Praevention\PraeventionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPraeventionNachweis extends EditRecord
{
    protected static string $resource = PraeventionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
