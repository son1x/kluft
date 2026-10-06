<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    public function getSubheading(): ?string
    {
        return 'Wer hat wann was getan – ohne Inhalte und Personendaten; IP-Adressen gekürzt. Einträge werden nach 12 Monaten automatisch gelöscht.';
    }
}
