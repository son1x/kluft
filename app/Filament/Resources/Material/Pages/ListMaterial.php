<?php

namespace App\Filament\Resources\Material\Pages;

use App\Filament\Resources\Material\MaterialResource;
use App\Support\Materialverleih;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaterial extends ListRecords
{
    protected static string $resource = MaterialResource::class;

    public function getSubheading(): ?string
    {
        return 'Was ihr verleiht. „Belegung“ zeigt je Material einen Kalender. '
            .(Materialverleih::oeffentlich() ? 'Anfragen kommen auch über die Website.' : 'Anfragen über die Website sind aus (Verwaltung → Funktionen).');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('website')->label('Auf der Website ansehen')->color('gray')->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => route('material.index'), shouldOpenInNewTab: true)
                ->visible(fn () => Materialverleih::oeffentlich()),
            CreateAction::make()->label('Material anlegen'),
        ];
    }
}
