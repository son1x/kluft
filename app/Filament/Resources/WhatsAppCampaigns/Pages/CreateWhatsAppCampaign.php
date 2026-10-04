<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsAppCampaign extends CreateRecord
{
    protected static string $resource = WhatsAppCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'status' => 'draft', 'created_by' => Filament::auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
