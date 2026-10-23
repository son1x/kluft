<?php

namespace App\Filament\Resources\Shop\Pages;

use App\Filament\Resources\Shop\SetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSets extends ManageRecords
{
    protected static string $resource = SetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Set anlegen')];
    }
}
