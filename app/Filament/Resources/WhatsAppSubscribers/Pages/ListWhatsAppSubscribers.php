<?php

namespace App\Filament\Resources\WhatsAppSubscribers\Pages;

use App\Filament\Resources\WhatsAppSubscribers\WhatsAppSubscriberResource;
use App\Models\WhatsAppSubscriber;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppSubscribers extends ListRecords
{
    protected static string $resource = WhatsAppSubscriberResource::class;

    public function getSubheading(): ?string
    {
        $active = WhatsAppSubscriber::query()->active()->count();
        $pending = WhatsAppSubscriber::query()->where('status', 'pending')->count();

        return "{$active} aktiv, {$pending} unbestätigt. Gespeichert sind nur die Nummer (verschlüsselt) und der Einwilligungsnachweis.";
    }
}
