<?php

namespace App\Filament\Resources\Adressbuch\Pages;

use App\Filament\Resources\Adressbuch\AdressbuchVerteilerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdressbuchVerteiler extends ListRecords
{
    protected static string $resource = AdressbuchVerteilerResource::class;

    public function getSubheading(): ?string
    {
        return 'Listen von Kontakten, z. B. für Einladungen zu Videokonferenzen oder zum Kopieren ins Mailprogramm.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Neuer Verteiler')];
    }
}
