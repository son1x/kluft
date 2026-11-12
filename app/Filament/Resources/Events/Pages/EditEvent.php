<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Actions\CopyForWhatsAppChannelAction;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Support\Zuschuesse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    /** Datum und Uhrzeit getrennt anzeigen (EventForm::zeitFelder). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Beginn/Ende als Ortszeit-Text (sonst stünde in den versteckten Feldern ein
        // UTC-Zeitstempel, und jedes Speichern verschöbe die Uhrzeit).
        return [
            ...$data,
            'starts_at' => $this->record->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $this->record->ends_at?->format('Y-m-d H:i:s'),
            ...EventForm::zeitFelder($this->record->starts_at, $this->record->ends_at, (bool) $this->record->ganztaegig),
        ];
    }

    /**
     * Formular und Teilnehmende als Reiter oben statt untereinander: Bei großen
     * Formularen (z. B. mit eigenen Feldern) musste man bis zu den Teilnehmenden
     * scrollen. Direkt hin: ?relation=0 (EventsTable → „Teilnehmende“).
     */
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
            Action::make('zuschussAntrag')
                ->label('Zuschussantrag')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('zuschuss', ['record' => $this->record]))
                ->visible(fn () => Zuschuesse::aktiv() && $this->record->zuschuss),
            CopyForWhatsAppChannelAction::make(),
            DeleteAction::make()
                ->modalDescription('Löscht die Veranstaltung samt aller Anmeldungen endgültig.'),
        ];
    }
}
