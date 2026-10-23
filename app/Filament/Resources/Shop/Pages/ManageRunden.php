<?php

namespace App\Filament\Resources\Shop\Pages;

use App\Filament\Resources\Shop\RundeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRunden extends ManageRecords
{
    protected static string $resource = RundeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Runde öffnen')];
    }
}
