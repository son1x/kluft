<?php

namespace App\Filament\Resources\Videokonferenzen\Pages;

use App\Filament\Resources\Videokonferenzen\VideokonferenzResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVideokonferenzen extends ListRecords
{
    protected static string $resource = VideokonferenzResource::class;

    public function getSubheading(): ?string
    {
        return 'Räume auf der Jitsi-Installation des DV: Teilnahme-Link und Einladung für alle, dazu ein Moderator-Link bis zum Ende des Folgetags.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Neue Videokonferenz')];
    }
}
