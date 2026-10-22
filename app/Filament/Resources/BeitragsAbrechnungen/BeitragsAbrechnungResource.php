<?php

namespace App\Filament\Resources\BeitragsAbrechnungen;

use App\Filament\Resources\Beitraege\BeitragResource;
use App\Filament\Resources\BeitragsAbrechnungen\Pages\ManageBeitragsAbrechnungen;
use App\Models\Beitrag;
use App\Models\BeitragsAbrechnung;
use App\Services\Beitraege\BeitragsAbruf;
use App\Support\Beitraege;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Abrechnung mit dem Bundesverband: je Halbjahr eine Rechnung, daneben
 * Anzahl und Summen aus der Beitragsliste zum Abgleich.
 */
class BeitragsAbrechnungResource extends Resource
{
    protected static ?string $model = BeitragsAbrechnung::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyEuro;

    protected static string|UnitEnum|null $navigationGroup = 'Kasse';

    protected static ?string $slug = 'kasse/abrechnungen';

    protected static ?string $navigationLabel = 'Bundesverbandsrechnung';

    protected static ?string $modelLabel = 'Rechnung';

    protected static ?string $pluralModelLabel = 'Rechnungen des Bundesverbands';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return BeitragResource::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('halbjahr')->label('Halbjahr')->required()
                ->options(fn () => Beitraege::auswahl(BeitragsAbrechnung::query()->pluck('halbjahr')->all()))
                ->default(Beitraege::aktuell())
                ->unique(ignoreRecord: true),
            TextInput::make('mitglieder')->label('Mitglieder laut Rechnung')->numeric()->minValue(0),
            TextInput::make('rechnungsbetrag_cent')->label('Rechnungsbetrag')->suffix('€')->rules(BeitragResource::EURO)->inputMode('decimal')
                ->formatStateUsing(fn ($state) => BeitragResource::alsEuro(is_numeric($state) ? (int) $state : null))
                ->dehydrateStateUsing(fn ($state) => BeitragResource::alsCent($state)),
            DatePicker::make('rechnungsdatum')->label('Rechnungsdatum'),
            DatePicker::make('bezahlt_am')->label('An den Bundesverband bezahlt am'),
            TextInput::make('notiz')->label('Notiz')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        $summe = fn (BeitragsAbrechnung $record, string $spalte) => (int) Beitrag::query()->where('halbjahr', $record->halbjahr)->sum($spalte);

        return $table
            ->defaultSort('halbjahr', 'desc')
            ->emptyStateHeading('Noch keine Rechnung eingetragen')
            ->columns([
                TextColumn::make('halbjahr')->label('Halbjahr')->formatStateUsing(fn (string $state) => Beitraege::label($state))->sortable(),
                TextColumn::make('mitglieder')->label('Mitglieder (Rechnung)')->placeholder('–')
                    ->description(function (BeitragsAbrechnung $record) {
                        $kluft = Beitrag::query()->where('halbjahr', $record->halbjahr)
                            ->where(fn ($q) => $q->whereNull('beitragsart')->orWhereNotIn('beitragsart', [Beitraege::UEBERNAHME, BeitragsAbruf::SCHNUPPER]))->count();

                        return "in Kluft: {$kluft}".($record->mitglieder !== null && $record->mitglieder !== $kluft ? ' – abweichend' : '');
                    }),
                TextColumn::make('rechnungsbetrag_cent')->label('Rechnung')->alignEnd()
                    ->formatStateUsing(fn (?int $state) => Beitraege::euro($state))->placeholder('–')
                    ->description(fn (BeitragsAbrechnung $record) => $record->rechnungsdatum ? 'vom '.$record->rechnungsdatum->format('d.m.Y') : null),
                TextColumn::make('bund')->label('Bundesbeitrag (Kluft)')->alignEnd()
                    ->state(fn (BeitragsAbrechnung $record) => Beitraege::euro($summe($record, 'bundes_cent')))
                    ->description(fn (BeitragsAbrechnung $record) => $record->rechnungsbetrag_cent !== null && $record->rechnungsbetrag_cent !== $summe($record, 'bundes_cent') ? 'abweichend von der Rechnung' : null),
                TextColumn::make('soll')->label('Soll gesamt (Kluft)')->alignEnd()
                    ->state(fn (BeitragsAbrechnung $record) => Beitraege::euro($summe($record, 'soll_cent'))),
                TextColumn::make('eingegangen')->label('Eingegangen')->alignEnd()
                    ->state(fn (BeitragsAbrechnung $record) => Beitraege::euro($summe($record, 'bezahlt_cent')))
                    ->description(function (BeitragsAbrechnung $record) {
                        $offen = Beitrag::query()->offen()->where('halbjahr', $record->halbjahr)->count();

                        return $offen ? "{$offen} offen" : 'alle bezahlt';
                    }),
                TextColumn::make('bezahlt_am')->label('An BV bezahlt')->date('d.m.Y')->placeholder('offen'),
                TextColumn::make('notiz')->label('Notiz')->placeholder('–')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBeitragsAbrechnungen::route('/'),
        ];
    }
}
