<?php

namespace App\Filament\Resources\Adressbuch\Pages;

use App\Filament\Resources\Adressbuch\AdressbuchKontaktResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdressbuchKontakt extends EditRecord
{
    protected static string $resource = AdressbuchKontaktResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
