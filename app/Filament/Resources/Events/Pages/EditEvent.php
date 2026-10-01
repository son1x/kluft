<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ansehen')
                ->label('Öffentliche Seite')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => route('events.show', $this->record->slug), shouldOpenInNewTab: true)
                ->visible(fn () => $this->record->status !== 'draft'),
            DeleteAction::make()
                ->modalDescription('Löscht die Veranstaltung samt aller Anmeldungen endgültig.'),
        ];
    }
}
