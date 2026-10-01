<?php

namespace App\Filament\Resources\Gruppierungen\Pages;

use App\Filament\Resources\Gruppierungen\GruppierungResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGruppierungen extends ListRecords
{
    protected static string $resource = GruppierungResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('oeffentlich')
                ->label('Öffentliche Seite')
                ->url(route('gruppierungen.index'))
                ->openUrlInNewTab()
                ->color('gray'),
            CreateAction::make(),
        ];
    }
}
