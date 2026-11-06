<?php

namespace App\Filament\Resources\Adressbuch\Pages;

use App\Filament\Resources\Adressbuch\AdressbuchKontaktResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdressbuchKontakte extends ListRecords
{
    protected static string $resource = AdressbuchKontaktResource::class;

    public function getSubheading(): ?string
    {
        return 'Gemeinsames Adressbuch, auch für Externe ohne Konto. Kontakte erscheinen in der Datenschutzauskunft und werden auf Anfrage gelöscht.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Neuer Kontakt')];
    }
}
