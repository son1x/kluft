<?php

namespace App\Filament\Resources\Adressbuch\Pages;

use App\Filament\Resources\Adressbuch\AdressbuchVerteilerResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateAdressbuchVerteiler extends CreateRecord
{
    protected static string $resource = AdressbuchVerteilerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Filament::auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return AdressbuchVerteilerResource::getUrl('index');
    }
}
