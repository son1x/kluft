<?php

namespace App\Filament\Resources\Gruppenstunden\Pages;

use App\Filament\Resources\Gruppenstunden\GruppenstundeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGruppenstunden extends ListRecords
{
    protected static string $resource = GruppenstundeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
