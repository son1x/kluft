<?php

namespace App\Filament\Resources\CalendarEntries\Pages;

use App\Filament\Resources\CalendarEntries\CalendarEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListCalendarEntries extends ListRecords
{
    protected static string $resource = CalendarEntryResource::class;

    public function getSubheading(): ?string
    {
        return 'Kommende Termine aus den Nextcloud-Kalendern. Für einen Termin ohne Anmeldung legst du mit „Anmeldung anlegen“ eine Veranstaltung an – Titel, Zeit, Ort und Stufe sind dann schon ausgefüllt.';
    }
}
