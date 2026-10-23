<?php

namespace App\Filament\Resources\InstagramKonten\Pages;

use App\Filament\Resources\InstagramKonten\InstagramKontoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstagramKonten extends ListRecords
{
    protected static string $resource = InstagramKontoResource::class;

    public function getSubheading(): ?string
    {
        return 'Neueste Beiträge eigener Instagram-Konten auf Startseite und Fachbereichen. Die Bilder liegen auf unserem Server; Besucher*innen laden nichts von Instagram, bis sie einen Beitrag anklicken.';
    }

    protected function getHeaderActions(): array
    {
        return [
            self::anleitung(),
            CreateAction::make(),
        ];
    }

    /** Schritt-für-Schritt: Token bei Meta erzeugen (auch im Formular „Neu“ verlinkt). */
    public static function anleitung(): Action
    {
        return Action::make('anleitung')
            ->label('Anleitung: Token erzeugen')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->slideOver()
            ->modalHeading('Instagram-Token erzeugen')
            ->modalContent(view('filament.instagram-anleitung'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Schließen');
    }
}
