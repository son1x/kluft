<?php

namespace App\Filament\Resources\Videokonferenzen\Pages;

use App\Filament\Resources\Videokonferenzen\VideokonferenzResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVideokonferenz extends ViewRecord
{
    protected static string $resource = VideokonferenzResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Der Raum verschwindet aus der Liste. Bereits verschickte Links funktionieren weiter: der Moderator-Link bis zu seinem Ablauf, der Teilnahme-Link ohnehin.'),
        ];
    }
}
