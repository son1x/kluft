<?php

namespace App\Filament\Resources\Adressbuch\Pages;

use App\Filament\Resources\Adressbuch\AdressbuchKontaktResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateAdressbuchKontakt extends CreateRecord
{
    protected static string $resource = AdressbuchKontaktResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Filament::auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return AdressbuchKontaktResource::getUrl('index');
    }
}
