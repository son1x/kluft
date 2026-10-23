<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\Pages\ManageRunden;
use App\Models\Shop\Runde;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                    ->color(fn (string $state) => $state === 'offen' ? 'success' : 'gray'),
                TextColumn::make('bestellungen_count')->counts(['bestellungen' => fn ($q) => $q->whereNotNull('bestaetigt_am')->whereNull('storniert_am')])->label('Bestellungen'),
            ])
            ->recordActions([
                Action::make('schliessen')->label('Schließen')->icon('heroicon-o-lock-closed')->color('warning')
                    ->visible(fn (Runde $record) => $record->istOffen())
                    ->requiresConfirmation()
                    ->modalDescription('Danach sind keine Bestellungen und keine Stornos über die Website mehr möglich. In die Sammelliste kommt nur, was bezahlt ist; Unbezahltes bitte unter „Bestellungen“ stornieren oder noch abwarten.')
                    ->action(function (Runde $record) {
                        $record->update(['status' => 'geschlossen', 'geschlossen_am' => now()]);
                        AuditLogger::log('sammelbestellung.runde.geschlossen', Filament::auth()->user(), 'shop_runden', $record->id, request());
                    }),
                Action::make('sammelliste')->label('Sammelliste')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->action(function (Runde $record): StreamedResponse {
                        abort_unless(static::canAccess(), 403);
                        AuditLogger::log('sammelbestellung.sammelliste', Filament::auth()->user(), 'shop_runden', $record->id, request());

                        return response()->streamDownload(function () use ($record) {
                            $out = fopen('php://output', 'w');
                            fwrite($out, "\xEF\xBB\xBF");
                            fputcsv($out, ['Rüsthaus-Nr.', 'Artikel', 'Größe', 'Anzahl'], ';');
                            foreach (Sammelbestellung::sammelliste($record) as $zeile) {
                                fputcsv($out, [$zeile['nummer'], $zeile['bezeichnung'], $zeile['groesse'], $zeile['menge']], ';');
                            }
                            fclose($out);
                        }, 'sammelliste-'.str($record->name)->slug().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
                Action::make('bestellungen')->label('Bestellungen')->icon('heroicon-o-inbox-stack')->color('gray')
                    ->url(fn (Runde $record) => BestellungResource::getUrl('index', ['filters' => ['runde_id' => ['value' => $record->id]]])),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRunden::route('/')];
    }
}
