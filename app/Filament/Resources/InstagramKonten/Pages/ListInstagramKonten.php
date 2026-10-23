<?php

namespace App\Filament\Resources\InstagramKonten\Pages;

use App\Filament\Resources\InstagramKonten\InstagramKontoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstagramKonten extends ListRecords
{
    protected static string $resource = InstagramKontoResource::class;

    public function getSubheading(): ?string
    {
        return 'Neueste Beiträge eigener Instagram-Konten auf Startseite und Fachbereichen. Die Bilder liegen auf unserem Server; Besucher*innen laden nichts von Instagram, bis sie einen Beitrag anklicken.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
