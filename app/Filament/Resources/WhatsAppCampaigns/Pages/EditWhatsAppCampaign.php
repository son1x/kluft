<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignActions;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\WhatsAppCampaign;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppCampaign extends EditRecord
{
    protected static string $resource = WhatsAppCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return WhatsAppCampaignActions::all();
    }

    protected function getFormActions(): array
    {
        $record = $this->getRecord();

        return $record instanceof WhatsAppCampaign && $record->isEditable() ? parent::getFormActions() : [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Nach Änderungen muss neu freigegeben werden.
        $record = $this->getRecord();
        if ($record instanceof WhatsAppCampaign && $record->status === 'review') {
            $data['status'] = 'draft';
        }

        return $data;
    }
}
