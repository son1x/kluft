<?php

namespace App\Filament\Resources\Praevention\Pages;

use App\Filament\Resources\Praevention\PraeventionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPraeventionNachweise extends ListRecords
{
    protected static string $resource = PraeventionResource::class;

    public function getSubheading(): ?string
    {
        return 'Führungszeugnis, Präventionsschulung und Juleica der Leitenden. Nur Daten, keine Dokumente. Ein Jahr nach Ablauf werden Nachweise gelöscht.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nachweis eintragen')];
    }
}
