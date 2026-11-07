<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\Mailversand;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    public function getSubheading(): ?string
    {
        $user = Filament::auth()->user();
        $alle = $user instanceof User && $user->isVerwaltung();

        return ($alle ? 'Alle Nachrichten aus dem Kontaktformular und Fragen zu Veranstaltungen.' : 'Fragen zu den Veranstaltungen deiner Stufe bzw. deines Bereichs.')
            .(Mailversand::nurProtokoll()
                ? ' Achtung: Mails werden derzeit nur ins Server-Protokoll geschrieben, die zuständigen Personen bekommen sie nicht (Verwaltung → E-Mail-Versand). Bitte weiterleiten oder direkt antworten.'
                : ' Die zuständige Person bekommt jede auch per Mail.')
            .' Gelöscht wird nach '.ContactMessage::aufbewahrungMonate().' Monaten.';
    }
}
