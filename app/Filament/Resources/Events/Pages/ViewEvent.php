<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Veranstaltung nur ansehen (docs/rollen.md): z. B. DL-Mitglieder, wenn die
 * Leitungen Veranstaltungen nur lesen. Wie beim Bearbeiten mit den Reitern
 * „Veranstaltung“ und „Teilnehmende“ (dort ohne Schreibaktionen).
 */
class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Veranstaltung';
    }

    public function getContentTabIcon(): string|BackedEnum|Htmlable|null
    {
        return Heroicon::OutlinedCalendarDays;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ansehen')
                ->label('Öffentliche Seite')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => route('events.show', $this->record->slug), shouldOpenInNewTab: true)
                ->visible(fn () => $this->record->status !== 'draft'),
            EditAction::make(),
        ];
    }
}
