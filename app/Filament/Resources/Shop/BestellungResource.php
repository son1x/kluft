<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\Pages\EditBestellung;
use App\Filament\Resources\Shop\Pages\ListBestellungen;
use App\Filament\Resources\Shop\RelationManagers\PositionenRelationManager;
use App\Models\Shop\Bestellung;
use App\Models\Shop\Runde;
use App\Support\Sammelbestellung;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Bestellungen einer Runde: Stand, freie Artikel prüfen und bestätigen,
 * stornieren. Bezahlung hakt die Kasse unter „Kasse → Zahlungen“ ab.
 */
class BestellungResource extends Resource
{
    use Zugriff;

    protected static ?string $model = Bestellung::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Sammelbestellung';

    protected static ?string $modelLabel = 'Bestellung';

    protected static ?string $pluralModelLabel = 'Bestellungen';

    protected static ?int $navigationSort = 15;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }
        $n = Bestellung::query()->whereNotNull('bestaetigt_am')->whereNull('storniert_am')
            ->whereHas('positionen', fn (Builder $q) => $q->where('bestaetigt', false))->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->components([
                TextInput::make('name')->label('Name')->disabled(),
                TextInput::make('email')->label('E-Mail')->disabled(),
                TextInput::make('telefon')->label('Telefon')->disabled(),
                TextInput::make('abholung')->label('Abholung')->disabled(),
                Textarea::make('bemerkung')->label('Bemerkung')->disabled()->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('positionen.zahlung', 'runde'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label('Bestellt von')->weight('bold')->searchable()->description(fn (Bestellung $record) => $record->email)
                    ->formatStateUsing(fn (Bestellung $record, string $state) => $record->test ? 'TEST: '.$state : $state),
                TextColumn::make('runde.name')->label('Runde'),
                TextColumn::make('mitglieder')->label('Für')->state(fn (Bestellung $record) => $record->positionen->pluck('mitglied')->unique()->implode(', '))->wrap(),
                TextColumn::make('summe')->label('Summe')->state(fn (Bestellung $record) => Sammelbestellung::euro($record->summeCent()))->alignEnd(),
                TextColumn::make('stand')->label('Stand')->badge()->state(fn (Bestellung $record) => $record->stand())
                    ->formatStateUsing(fn (string $state) => Bestellung::STAENDE[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'bezahlt' => 'success', 'pruefung' => 'warning', 'zahlung' => 'info', default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Eingang')->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                SelectFilter::make('runde_id')->label('Runde')->options(fn () => Runde::query()->orderByDesc('id')->pluck('name', 'id')->all()),
            ])
            ->recordActions([EditAction::make()->label('Öffnen')]);
    }

    public static function getRelations(): array
    {
        return [PositionenRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListBestellungen::route('/'), 'edit' => EditBestellung::route('/{record}/edit')];
    }
}
