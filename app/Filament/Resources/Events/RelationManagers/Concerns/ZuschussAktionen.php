<?php

namespace App\Filament\Resources\Events\RelationManagers\Concerns;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Person;
use App\Services\EventRegistrations;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\AuditLogger;
use App\Support\NextcloudAblage;
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
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
            Action::make('zuschussLeitung')->label('Leitung aus Personen übernehmen')->icon('heroicon-o-user-group')
                ->modalDescription('Trägt die gewählten Personen als bestätigt ein, mit den hinterlegten Angaben für Zuschussanträge (Personen → „Für Zuschussanträge“). Wer schon angemeldet ist, wird übersprungen.')
                ->schema([
                    CheckboxList::make('personen')->label('Personen')->required()->searchable()->columns(2)
                        ->options(fn () => Person::query()->where('active', true)->orderBy('name')->get()
                            ->mapWithKeys(fn (Person $p) => [$p->id => $p->name.($p->role ? ' ('.$p->role.')' : '').(filled($p->zuschuss_daten) ? '' : ' – ohne Angaben')])->all()),
                    Select::make('funktion')->label('Funktion')->options(['L' => 'Leiter*in', 'B' => 'Betreuer*in', 'R' => 'Referent*in'])->default('B')->required(),
                ])
                ->action(fn (array $data) => $this->leitungUebernehmen((array) $data['personen'], (string) $data['funktion'])),
            Action::make('zuschussNextcloud')->label('In Nextcloud ablegen')->icon('heroicon-o-cloud-arrow-up')
                ->visible(fn () => NextcloudAblage::eingerichtet())
                ->action(function () {
                    $event = $this->getOwnerRecord();
                    abort_unless($event instanceof Event && Filament::auth()->user()?->can('manageParticipants', $event), 403);
                    try {
                        $n = NextcloudAblage::abgleichen($event);
                        AuditLogger::log('event.zuschuss_nextcloud', Filament::auth()->user(), Event::class, $event->getKey(), request());
                        Notification::make()->success()->title($n.' Datei(en) in „'.NextcloudAblage::ordner($event).'“ abgelegt')->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Nextcloud-Ablage fehlgeschlagen')->body($e->getMessage())->send();
                    }
                }),
            Action::make('zuschussUebersicht')->label('Übersicht und Schätzung')->icon('heroicon-o-calculator')
                ->modalSubmitAction(false)->modalCancelActionLabel('Schließen')->modalWidth('3xl')
                ->modalContent(fn () => new HtmlString(view('filament.zuschuss-uebersicht', [
                    'event' => $this->getOwnerRecord(),
                    'zaehlung' => Zuschuesse::zaehlung($this->getOwnerRecord()),
                    'fehlend' => Zuschuesse::fehlend($this->getOwnerRecord()),
                    'schaetzung' => Foerderrechner::rechnen($this->getOwnerRecord()),
                ])->render())),
            ...collect(ZuschussPdf::UNTERLAGEN)->map(fn (array $u, string $art) => Action::make('zuschuss_'.$art)->label($u[0].' (PDF)')
                ->icon($art === 'schaetzung' ? 'heroicon-o-document-chart-bar' : 'heroicon-o-document-text')
                ->visible(fn () => array_key_exists($art, ZuschussPdf::unterlagen($this->getOwnerRecord())))
                ->action(fn () => $this->zuschussPdf($art)))->values()->all(),
        ])->label('Zuschuss')->icon('heroicon-o-banknotes')->button()->color('gray')->visible($event->zuschussAktiv());
    }

    /** @param list<int|string> $ids */
    private function leitungUebernehmen(array $ids, string $funktion): void
    {
        $event = $this->getOwnerRecord();
        abort_unless($event instanceof Event && Filament::auth()->user()?->can('manageParticipants', $event), 403);
        $felder = array_keys($event->formFields());
        $neu = 0;
        foreach (Person::query()->whereKey($ids)->get() as $person) {
            $daten = (array) $person->zuschuss_daten;
            $name = trim((string) $person->name);
            $pos = mb_strrpos($name, ' ');
            $eintrag = [
                'first_name' => $pos === false ? $name : mb_substr($name, 0, $pos),
                'last_name' => $pos === false ? '–' : mb_substr($name, $pos + 1),
                'email' => '',
                'data' => array_intersect_key($daten, array_flip($felder)),
            ];
            [, $registrierung] = app(EventRegistrations::class)->addManually($event, $eintrag, 'confirmed', false, Filament::auth()->user());
            if ($registrierung) {
                $registrierung->update(['funktion' => $funktion, 'juleica' => Zuschuesse::juleicaGueltig($daten, $event)]);
                $neu++;
            }
        }
        Notification::make()->success()->title($neu.' Person(en) übernommen')->body(count($ids) > $neu ? (count($ids) - $neu).' waren schon angemeldet.' : null)->send();
    }

    private function zuschussPdf(string $art): StreamedResponse
    {
        $event = $this->getOwnerRecord();
        abort_unless($event instanceof Event && Filament::auth()->user()?->can('manageParticipants', $event), 403);
        AuditLogger::log('event.zuschuss_'.$art, Filament::auth()->user(), Event::class, $event->getKey(), request());
        $pdf = app(ZuschussPdf::class)->unterlage($event, $art);

        return response()->streamDownload(fn () => print ($pdf), ZuschussPdf::dateiname($event, $art), ['Content-Type' => 'application/pdf']);
    }
}
