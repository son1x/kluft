<?php

namespace App\Filament\Resources\Material\Pages;

use App\Filament\Resources\Material\MaterialResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaterial extends EditRecord
{
    protected static string $resource = MaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('belegung')->label('Belegung')->color('gray')->icon('heroicon-o-calendar-days')
                ->url(fn () => MaterialResource::getUrl('belegung', ['record' => $this->record])),
            DeleteAction::make()->modalDescription('Löscht auch alle Reservierungen dieses Materials. Ausgemustertes Material besser auf „nicht verleihbar“ stellen.'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
