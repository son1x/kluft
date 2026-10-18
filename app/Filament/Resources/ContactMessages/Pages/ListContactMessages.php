<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    public function getSubheading(): ?string
    {
        return 'Alle Nachrichten aus dem Kontaktformular und Fragen zu Veranstaltungen. Die zuständige Person hat jede auch per Mail bekommen. Gelöscht wird nach '
            .ContactMessage::aufbewahrungMonate().' Monaten.';
    }
}
