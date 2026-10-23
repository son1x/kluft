<?php

namespace App\Filament\Resources\Shop\Pages;

use App\Filament\Resources\Shop\BestellungResource;
use App\Models\Shop\Bestellung;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBestellung extends EditRecord
{
    protected static string $resource = BestellungResource::class;

    public function getTitle(): string
    {
        /** @var Bestellung $b */
        $b = $this->getRecord();

        return 'Bestellung von '.$b->name.' – '.(Bestellung::STAENDE[$b->stand()] ?? '');
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('stornieren')->label('Stornieren')->color('danger')->icon('heroicon-o-x-circle')
                ->visible(fn () => ! $this->getRecord()->storniert_am)
                ->requiresConfirmation()
                ->modalDescription('Offene Zahlungen entfallen. Bereits Bezahltes bleibt in „Kasse → Zahlungen“ mit dem Hinweis „Erstattung prüfen“.')
                ->action(function () {
                    Sammelbestellung::stornieren($this->getRecord(), Filament::auth()->id());
                    AuditLogger::log('sammelbestellung.bestellung.storniert', Filament::auth()->user(), 'shop_bestellungen', $this->getRecord()->id, request());
                    Notification::make()->title('Storniert')->success()->send();
                }),
        ];
    }
}
