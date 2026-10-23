<?php

namespace App\Filament\Resources\Shop\Pages;

use App\Filament\Resources\Shop\BestellungResource;
use App\Models\Shop\Bestellung;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBestellungen extends ListRecords
{
    protected static string $resource = BestellungResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testsLoeschen')->label('Testbestellungen löschen')->icon('heroicon-o-trash')->color('danger')
                ->visible(fn () => Bestellung::query()->where('test', true)->exists())
                ->requiresConfirmation()
                ->modalDescription('Löscht alle im Testbetrieb aufgegebenen Bestellungen samt ihren Zahlungen (auch in „Kasse → Zahlungen“).')
                ->action(function () {
                    $n = Sammelbestellung::testsLoeschen();
                    AuditLogger::log('sammelbestellung.tests_geloescht', auth()->user(), Bestellung::class, null, request());
                    Notification::make()->success()->title($n.' Testbestellung(en) gelöscht')->send();
                }),
        ];
    }
}
