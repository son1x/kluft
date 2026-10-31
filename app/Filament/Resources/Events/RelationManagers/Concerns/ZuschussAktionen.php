<?php

namespace App\Filament\Resources\Events\RelationManagers\Concerns;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\AuditLogger;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Foerderrechner;
use App\Support\Zuschuesse\Kreise;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Zuschussanträge in der Teilnehmendenliste (docs/pflichtenheft-zuschuesse.md):
 * Funktion, Juleica, Kreis und Anwesenheit je Person, Übersicht mit fehlenden
 * Angaben, Teilnahmeliste und Förderschätzung als PDF. Nur sichtbar, wenn
 * die Veranstaltung „Zuschuss beantragen“ hat.
 */
trait ZuschussAktionen
{
    /** @return list<TextColumn> */
    protected function zuschussSpalten(Event $event): array
    {
        if (! $event->zuschussAktiv()) {
            return [];
        }

        return [
            TextColumn::make('funktion')->label('Funktion')->badge()
                ->formatStateUsing(fn (?string $state) => $state ?: 'TN')
                ->tooltip(fn (EventRegistration $record) => Zuschuesse::FUNKTIONEN[$record->funktion ?: 'TN'] ?? null)
                ->color(fn (?string $state) => Zuschuesse::istBetreuung((string) $state) ? 'info' : 'gray'),
            TextColumn::make('anwesend')->label('Anwesend')
                ->state(fn (EventRegistration $record) => match (true) {
                    $record->anwesend === false => 'nicht da',
                    filled($record->anwesend_tage) => count($record->anwesend_tage).' Tag(e)',
                    default => 'ja',
                })
                ->color(fn (string $state) => $state === 'nicht da' ? 'danger' : 'success')->badge(),
        ];
    }

    /** Eingaben beim Eintragen von Hand. @return list<Select|Toggle> */
    protected function zuschussEingaben(Event $event): array
    {
        if (! $event->zuschussAktiv()) {
            return [];
        }

        return [
            Select::make('funktion')->label('Funktion')->options(Zuschuesse::FUNKTIONEN)->default('TN')->selectablePlaceholder(false)->live(),
            Toggle::make('juleica')->label('Juleica vorhanden')->visible(fn (Get $get) => Zuschuesse::istBetreuung((string) $get('funktion'))),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function zuschussNachEintragen(?EventRegistration $registration, array $data): void
    {
        if ($registration && isset($data['funktion'])) {
            $registration->update(['funktion' => $data['funktion'], 'juleica' => (bool) ($data['juleica'] ?? false)]);
        }
    }

    protected function zuschussAktion(Event $event): Action
    {
        $tage = $event->days();

        return Action::make('zuschuss')->label('Zuschuss')->icon('heroicon-o-banknotes')->color('gray')
            ->visible(fn (EventRegistration $record) => $event->zuschussAktiv() && $record->status === 'confirmed')
            ->modalHeading(fn (EventRegistration $record) => 'Zuschuss-Angaben: '.$record->fullName())
            ->fillForm(fn (EventRegistration $record) => [
                'funktion' => $record->funktion ?: 'TN', 'juleica' => $record->juleica, 'kreis' => $record->kreis,
                'anwesend' => $record->anwesend !== false, 'anwesend_tage' => $record->anwesend_tage ?: array_keys($tage),
            ])
            ->schema([
                Select::make('funktion')->label('Funktion')->options(Zuschuesse::FUNKTIONEN)->required()->live(),
                Toggle::make('juleica')->label('Juleica vorhanden')->visible(fn (Get $get) => Zuschuesse::istBetreuung((string) $get('funktion'))),
                Select::make('kreis')->label('Kreis (nur wenn die PLZ nicht passt)')->options(Kreise::NAMEN)
                    ->placeholder(fn (EventRegistration $record) => 'aus der PLZ: '.Kreise::NAMEN[Kreise::ausPlz($record->data['plz'] ?? null)]),
                Toggle::make('anwesend')->label('War dabei')->live(),
                CheckboxList::make('anwesend_tage')->label('An diesen Tagen')->options($tage)->columns(3)
                    ->visible(fn (Get $get) => count($tage) > 1 && (bool) $get('anwesend')),
            ])
            ->action(function (EventRegistration $record, array $data) use ($tage) {
                abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);
                $alle = array_keys($tage);
                $gewaehlt = array_values(array_intersect($alle, (array) ($data['anwesend_tage'] ?? [])));
                $record->update([
                    'funktion' => $data['funktion'], 'juleica' => (bool) ($data['juleica'] ?? false), 'kreis' => $data['kreis'] ?: null,
                    'anwesend' => (bool) $data['anwesend'],
                    // Alle Tage = Standard, dann nichts speichern.
                    'anwesend_tage' => $data['anwesend'] && count($alle) > 1 && $gewaehlt !== $alle ? $gewaehlt : null,
                ]);
                AuditLogger::log('event.zuschuss_angaben', Filament::auth()->user(), EventRegistration::class, $record->getKey(), request());
            });
    }

    protected function zuschussSammelaktionen(Event $event): BulkActionGroup
    {
        $setzen = function (Collection $records, array $werte, string $log) {
            abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);
            $records->each(fn (EventRegistration $r) => $r->event_id === $this->getOwnerRecord()->getKey() ? $r->update($werte) : null);
            AuditLogger::log($log, Filament::auth()->user(), Event::class, $this->getOwnerRecord()->getKey(), request());
        };

        return BulkActionGroup::make([
            BulkAction::make('anwesend')->label('War dabei')->icon('heroicon-o-check')->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => $setzen($records, ['anwesend' => true, 'anwesend_tage' => null], 'event.zuschuss_anwesend')),
            BulkAction::make('nichtAnwesend')->label('War nicht dabei')->icon('heroicon-o-x-mark')->color('danger')->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => $setzen($records, ['anwesend' => false, 'anwesend_tage' => null], 'event.zuschuss_anwesend')),
            BulkAction::make('funktionSetzen')->label('Funktion setzen')->icon('heroicon-o-identification')->deselectRecordsAfterCompletion()
                ->schema([
                    Select::make('funktion')->label('Funktion')->options(Zuschuesse::FUNKTIONEN)->required()->live(),
                    Toggle::make('juleica')->label('Juleica vorhanden')->visible(fn (Get $get) => Zuschuesse::istBetreuung((string) $get('funktion'))),
                ])
                ->action(fn (Collection $records, array $data) => $setzen($records, ['funktion' => $data['funktion'], 'juleica' => (bool) ($data['juleica'] ?? false)], 'event.zuschuss_funktion')),
        ])->label('Zuschuss')->visible($event->zuschussAktiv());
    }

    protected function zuschussKopfaktionen(Event $event): ActionGroup
    {
        return ActionGroup::make([
            Action::make('zuschussUebersicht')->label('Übersicht und Schätzung')->icon('heroicon-o-calculator')
                ->modalSubmitAction(false)->modalCancelActionLabel('Schließen')->modalWidth('3xl')
                ->modalContent(fn () => new HtmlString(view('filament.zuschuss-uebersicht', [
                    'event' => $this->getOwnerRecord(),
                    'zaehlung' => Zuschuesse::zaehlung($this->getOwnerRecord()),
                    'fehlend' => Zuschuesse::fehlend($this->getOwnerRecord()),
                    'schaetzung' => Foerderrechner::rechnen($this->getOwnerRecord()),
                ])->render())),
            Action::make('zuschussListe')->label('Teilnahmeliste (PDF)')->icon('heroicon-o-document-text')
                ->action(fn () => $this->zuschussPdf('liste')),
            Action::make('zuschussSchaetzung')->label('Förderschätzung (PDF)')->icon('heroicon-o-document-chart-bar')
                ->action(fn () => $this->zuschussPdf('schaetzung')),
        ])->label('Zuschuss')->icon('heroicon-o-banknotes')->button()->color('gray')->visible($event->zuschussAktiv());
    }

    private function zuschussPdf(string $art): StreamedResponse
    {
        $event = $this->getOwnerRecord();
        abort_unless($event instanceof Event && Filament::auth()->user()?->can('manageParticipants', $event), 403);
        AuditLogger::log('event.zuschuss_'.$art, Filament::auth()->user(), Event::class, $event->getKey(), request());
        $pdf = $art === 'liste' ? app(ZuschussPdf::class)->teilnahmeliste($event) : app(ZuschussPdf::class)->schaetzung($event);

        return response()->streamDownload(fn () => print ($pdf), ($art === 'liste' ? 'teilnahmeliste-' : 'foerderschaetzung-').str($event->title)->slug().'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
