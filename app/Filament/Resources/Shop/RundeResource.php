<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\Pages\ManageRunden;
use App\Models\Shop\Artikel;
use App\Models\Shop\Runde;
use App\Services\Sammelbestellung\Ausgabe;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use App\Support\SammelbestellungAblauf;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Bestellrunden: von Hand öffnen und schließen; Bestelldatum = Zahlungsfrist.
 * Sammelliste der bezahlten Positionen als CSV für den Rüsthaus-Warenkorb.
 */
class RundeResource extends Resource
{
    use Zugriff;

    /** Auch die Verwaltung: Ausgabe abhaken, Ausgabelisten (SB-43, SB-73). */
    public static function canAccess(): bool
    {
        return SammelbestellungAblauf::darfAusgeben(Filament::auth()->user());
    }

    protected static ?string $model = Runde::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Sammelbestellung';

    protected static ?string $modelLabel = 'Bestellrunde';

    protected static ?string $pluralModelLabel = 'Bestellrunden';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(80)->placeholder('z. B. Herbst 2026'),
            DatePicker::make('bestelldatum')->label('Bestelldatum (zugleich Zahlungsfrist)')->required()->native(false)->displayFormat('d.m.Y'),
            Select::make('status')->label('Status')->options(Runde::STATUS)->default('offen')->required()->native(false)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('status', 'offen'))
                ->validationMessages(['unique' => 'Es ist schon eine Runde offen. Bitte zuerst schließen.']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label('Runde')->weight('bold'),
                TextColumn::make('bestelldatum')->label('Bestelldatum')->date('d.m.Y'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state) => Runde::STATUS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'offen' => 'success', 'geschlossen', 'bestellt' => 'warning', 'geliefert' => 'info', default => 'gray'
                    }),
                TextColumn::make('abrechnung')->label('Eingang / Rechnung')
                    ->state(function (Runde $record) {
                        $a = SammelbestellungAblauf::abrechnung($record);

                        return Sammelbestellung::euro($a['eingang']).($a['rechnung'] !== null ? ' / '.Sammelbestellung::euro($a['rechnung']) : '');
                    })
                    ->description(function (Runde $record) {
                        $a = SammelbestellungAblauf::abrechnung($record);

                        return $a['differenz'] === null ? null : 'Differenz (Provision − Versand): '.Sammelbestellung::euro($a['differenz']);
                    }),
                TextColumn::make('bestellungen_count')->counts(['bestellungen' => fn ($q) => $q->whereNotNull('bestaetigt_am')->whereNull('storniert_am')])->label('Bestellungen'),
            ])
            ->recordActions([
                Action::make('bestellungen')->label('Bestellungen')->icon('heroicon-o-inbox-stack')->color('gray')
                    ->url(fn (Runde $record) => BestellungResource::getUrl('index', ['filters' => ['runde_id' => ['value' => $record->id]]])),
                ActionGroup::make([
                    Action::make('erinnern')->label('Zahlungserinnerung senden')->icon('heroicon-o-bell-alert')
                        ->visible(fn (Runde $record) => $record->istOffen())
                        ->requiresConfirmation()
                        ->modalDescription('Alle mit offenem Betrag bekommen die Zahlungsaufforderung noch einmal als Erinnerung (je Bestellung einmal). Das passiert sonst automatisch am Tag vor dem Bestelldatum.')
                        ->action(function (Runde $record) {
                            $n = SammelbestellungAblauf::erinnern($record);
                            AuditLogger::log('sammelbestellung.runde.erinnert', Filament::auth()->user(), 'shop_runden', $record->id, request());
                            Notification::make()->success()->title($n.' Erinnerung(en) verschickt')->send();
                        }),
                    Action::make('schliessen')->label('Schließen')->icon('heroicon-o-lock-closed')->color('warning')
                        ->visible(fn (Runde $record) => $record->istOffen())
                        ->requiresConfirmation()
                        ->modalDescription('Danach sind keine Bestellungen und Stornos über die Website mehr möglich. Unbezahltes wird storniert, die Personen bekommen eine Mail; gehaltene Bestellungen bleiben (unter „Bestellungen“ halten). In die Sammelliste kommt nur Bezahltes.')
                        ->action(function (Runde $record) {
                            $n = SammelbestellungAblauf::schliessen($record, Filament::auth()->id());
                            AuditLogger::log('sammelbestellung.runde.geschlossen', Filament::auth()->user(), 'shop_runden', $record->id, request());
                            Notification::make()->success()->title('Runde geschlossen')->body($n ? $n.' Bestellung(en) wegen fehlender Zahlung (teilweise) storniert.' : null)->send();
                        }),
                    Action::make('bestellt')->label('Beim Rüsthaus bestellt')->icon('heroicon-o-shopping-cart')
                        ->visible(fn (Runde $record) => $record->status === 'geschlossen')
                        ->requiresConfirmation()
                        ->action(function (Runde $record) {
                            SammelbestellungAblauf::bestellt($record);
                            AuditLogger::log('sammelbestellung.runde.bestellt', Filament::auth()->user(), 'shop_runden', $record->id, request());
                        }),
                    Action::make('geliefert')->label('Ware ist da')->icon('heroicon-o-gift')
                        ->visible(fn (Runde $record) => $record->status === 'bestellt')
                        ->modalDescription('Alle mit bezahlten Artikeln bekommen eine Mail, dass die Ware da ist.')
                        ->schema([Textarea::make('abholung')->label('Hinweis zur Abholung')->rows(2)->maxLength(500)
                            ->default('Wir geben sie in der nächsten Gruppenstunde aus.')])
                        ->action(function (Runde $record, array $data) {
                            $n = SammelbestellungAblauf::geliefert($record, $data['abholung'] ?? null);
                            AuditLogger::log('sammelbestellung.runde.geliefert', Filament::auth()->user(), 'shop_runden', $record->id, request());
                            Notification::make()->success()->title($n.' Mail(s) „Ware ist da“ verschickt')->send();
                        }),
                    Action::make('erledigt')->label('Erledigt')->icon('heroicon-o-check-badge')
                        ->visible(fn (Runde $record) => $record->status === 'geliefert')
                        ->requiresConfirmation()
                        ->modalDescription(fn () => 'Alles ist ausgegeben. Nach '.SammelbestellungAblauf::loeschfristMonate().' Monaten werden Namen und Adressen dieser Runde gelöscht; Summen und Zahlungen bleiben für die Kasse.')
                        ->action(function (Runde $record) {
                            SammelbestellungAblauf::erledigt($record);
                            AuditLogger::log('sammelbestellung.runde.erledigt', Filament::auth()->user(), 'shop_runden', $record->id, request());
                        }),
                    Action::make('rechnung')->label('Rüsthaus-Rechnung')->icon('heroicon-o-document-currency-euro')
                        ->visible(fn (Runde $record) => ! $record->istOffen())
                        ->fillForm(fn (Runde $record) => $record->only(['rechnung_cent', 'versand_cent', 'rechnung_am']))
                        ->modalDescription('Rechnungsbetrag nach Abzug der Provision und Versandkosten. Kluft zeigt die Differenz zu den eingegangenen Zahlungen.')
                        ->schema([
                            ArtikelResource::euroFeld(TextInput::make('rechnung_cent')->label('Rechnungsbetrag (nach Provision)'))->required(),
                            ArtikelResource::euroFeld(TextInput::make('versand_cent')->label('Versandkosten (trägt der Stamm)')),
                            DatePicker::make('rechnung_am')->label('Rechnungsdatum')->native(false)->displayFormat('d.m.Y'),
                        ])
                        ->action(function (Runde $record, array $data) {
                            $record->update($data);
                            AuditLogger::log('sammelbestellung.runde.rechnung', Filament::auth()->user(), 'shop_runden', $record->id, request());
                        }),
                    Action::make('sammelliste')->label('Sammelliste Rüsthaus (CSV)')->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Runde $record) => static::csv($record, false)),
                    Action::make('eigene')->label('Liste eigene Artikel (CSV)')->icon('heroicon-o-archive-box')
                        ->visible(fn () => Artikel::query()->where('eigen', true)->exists())
                        ->action(fn (Runde $record) => static::csv($record, true)),
                    Action::make('ausgabeliste')->label('Ausgabeliste (PDF)')->icon('heroicon-o-clipboard-document-list')
                        ->visible(fn (Runde $record) => ! $record->istOffen())
                        ->action(fn (Runde $record) => static::pdf($record, 'liste')),
                    Action::make('ausgabescheine')->label('Ausgabescheine (PDF)')->icon('heroicon-o-document-duplicate')
                        ->visible(fn (Runde $record) => ! $record->istOffen())
                        ->action(fn (Runde $record) => static::pdf($record, 'scheine')),
                    EditAction::make(),
                ])->label('Ablauf')->button()->color('gray'),
            ]);
    }

    /** Sammelliste der bezahlten Positionen: Rüsthaus-Artikel bzw. eigene Artikel des Stamms. */
    public static function csv(Runde $runde, bool $eigene): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        AuditLogger::log('sammelbestellung.sammelliste'.($eigene ? '.eigene' : ''), Filament::auth()->user(), 'shop_runden', $runde->id, request());

        return response()->streamDownload(function () use ($runde, $eigene) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$eigene ? 'Nr.' : 'Rüsthaus-Nr.', 'Artikel', 'Größe', 'Anzahl'], ';');
            foreach (Sammelbestellung::sammelliste($runde, $eigene) as $zeile) {
                fputcsv($out, [$eigene ? '' : $zeile['nummer'], $zeile['bezeichnung'], $zeile['groesse'], $zeile['menge']], ';');
            }
            fclose($out);
        }, ($eigene ? 'eigene-artikel-' : 'sammelliste-').str($runde->name)->slug().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Ausgabeliste bzw. -scheine mit Namen: nur Sammelbestellung, Verwaltung, Admin (SB-73). */
    public static function pdf(Runde $runde, string $art): StreamedResponse
    {
        abort_unless(SammelbestellungAblauf::darfAusgeben(Filament::auth()->user()), 403);
        AuditLogger::log('sammelbestellung.ausgabe.'.$art, Filament::auth()->user(), 'shop_runden', $runde->id, request());
        $pdf = app(Ausgabe::class)->pdf($runde, $art);

        return response()->streamDownload(fn () => print ($pdf), ($art === 'liste' ? 'ausgabeliste-' : 'ausgabescheine-').str($runde->name)->slug().'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRunden::route('/')];
    }
}
