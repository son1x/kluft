<?php

namespace App\Filament\Resources\Shop\Pages;

use App\Filament\Resources\Shop\ArtikelResource;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use InvalidArgumentException;

class ListArtikel extends ListRecords
{
    protected static string $resource = ArtikelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('holen')->label('Artikel holen')->icon('heroicon-o-arrow-down-tray')
                ->modalHeading('Artikel vom Rüsthaus holen und freigeben')
                ->modalDescription('Artikelnummer (auch einer bestimmten Größe) oder Link zur Produktseite. Name, Beschreibung, Bild, Preise und Größen werden gespeichert.')
                ->schema([TextInput::make('eingabe')->label('Rüsthaus-Artikelnummer oder Link')->required()->maxLength(300)->placeholder('z. B. 0100003.30')])
                ->action(function (array $data, Action $action) {
                    abort_unless(ArtikelResource::canAccess(), 403);
                    try {
                        $artikel = Sammelbestellung::freigeben($data['eingabe']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        $action->halt();

                        return;
                    }
                    AuditLogger::log('sammelbestellung.artikel.freigegeben', Filament::auth()->user(), 'shop_artikel', $artikel->id, request());
                    Notification::make()->title('„'.$artikel->name.'“ mit '.$artikel->varianten->count().' Größen freigegeben')->success()->send();
                }),
        ];
    }
}
