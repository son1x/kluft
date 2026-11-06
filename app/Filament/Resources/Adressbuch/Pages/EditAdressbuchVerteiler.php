<?php

namespace App\Filament\Resources\Adressbuch\Pages;

use App\Filament\Resources\Adressbuch\AdressbuchVerteilerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdressbuchVerteiler extends EditRecord
{
    protected static string $resource = AdressbuchVerteilerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdressbuchVerteilerResource::kopierenAktion(),
            AdressbuchVerteilerResource::einfuegenAktion(),
            DeleteAction::make()->modalDescription('Der Verteiler wird gelöscht, die Kontakte bleiben im Adressbuch.'),
        ];
    }
}
