<?php

namespace App\Filament\Resources\Shop\Pages;

use App\Filament\Resources\Shop\SperreResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSperren extends ManageRecords
{
    protected static string $resource = SperreResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
