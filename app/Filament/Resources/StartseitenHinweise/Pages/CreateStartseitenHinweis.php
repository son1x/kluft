<?php

namespace App\Filament\Resources\StartseitenHinweise\Pages;

use App\Filament\Resources\StartseitenHinweise\StartseitenHinweisResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStartseitenHinweis extends CreateRecord
{
    protected static string $resource = StartseitenHinweisResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
