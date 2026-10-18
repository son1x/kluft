<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use App\Models\User;
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
            .' Die zuständige Person hat jede auch per Mail bekommen. Gelöscht wird nach '.ContactMessage::aufbewahrungMonate().' Monaten.';
    }
}
