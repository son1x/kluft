<?php

namespace App\Filament\Resources\StartseitenHinweise\Pages;

use App\Filament\Resources\StartseitenHinweise\StartseitenHinweisResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStartseitenHinweise extends ListRecords
{
    protected static string $resource = StartseitenHinweisResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
