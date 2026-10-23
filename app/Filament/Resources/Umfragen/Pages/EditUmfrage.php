<?php

namespace App\Filament\Resources\Umfragen\Pages;

use App\Filament\Resources\Umfragen\UmfrageResource;
use App\Models\Umfrage;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUmfrage extends EditRecord
{
    protected static string $resource = UmfrageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('vorschau')->label('Vorschau')->icon('heroicon-o-eye')->color('gray')
                ->hidden(fn (Umfrage $record) => $record->ist_vorlage)
                ->url(fn (Umfrage $record) => route('umfragen.vorschau', $record), shouldOpenInNewTab: true),
            Action::make('auswertung')->label('Auswertung')->icon('heroicon-o-chart-bar')->color('gray')
                ->hidden(fn (Umfrage $record) => $record->ist_vorlage)
                ->url(fn (Umfrage $record) => UmfrageResource::getUrl('auswertung', ['record' => $record])),
            UmfrageResource::einladenAction(),
            DeleteAction::make()->modalDescription('Die Umfrage wird mit allen Antworten und Gewinnspiel-Einträgen gelöscht.'),
        ];
    }

    protected function afterSave(): void
    {
        AuditLogger::log('umfrage.geaendert', Filament::auth()->user(), Umfrage::class, $this->getRecord()->getKey(), request());
    }
}
