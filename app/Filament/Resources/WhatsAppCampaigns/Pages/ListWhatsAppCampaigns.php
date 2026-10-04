<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Services\WhatsApp\WhatsAppSettings;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppCampaigns extends ListRecords
{
    protected static string $resource = WhatsAppCampaignResource::class;

    public function getSubheading(): ?string
    {
        return app(WhatsAppSettings::class)->configured()
            ? 'Ablauf: Entwurf → Zur Freigabe → Freigeben (zweite Person) → Jetzt senden oder geplant.'
            : 'WhatsApp ist noch nicht mit Meta verbunden (WhatsApp-Newsletter → Verbindung).';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
