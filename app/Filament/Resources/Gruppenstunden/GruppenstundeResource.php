<?php

namespace App\Filament\Resources\Gruppenstunden;

use App\Filament\Resources\Gruppenstunden\Pages\CreateGruppenstunde;
use App\Filament\Resources\Gruppenstunden\Pages\EditGruppenstunde;
use App\Filament\Resources\Gruppenstunden\Pages\ListGruppenstunden;
use App\Models\Gruppenstunde;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Gruppenstunden der Stufen (nur Stammes-Websites). Mehrere Gruppen einer
 * Stufe sind möglich; Startseite und Stufenseite zeigen sie an.
 */
class GruppenstundeResource extends Resource
{
    protected static ?string $model = Gruppenstunde::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Gruppenstunde';

    protected static ?string $pluralModelLabel = 'Gruppenstunden';

    protected static ?int $navigationSort = 45;

    public static function canAccess(): bool
    {
        return config('dpsg.site.level') === 'Stamm' && parent::canAccess();
    }

    /** Stufenadmins sehen nur ihre Stufen. */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        return $user && ! $user->isVerwaltung() ? $query->whereIn('stage', $user->scopeKeysFor('stufenadmin')) : $query;
    }

    /** @return array<string, string> */
    private static function stufen(): array
    {
        $user = auth()->user();
        $alle = collect(config('dpsg.stages'))->map(fn (array $s) => $s['label']);

        return ($user && ! $user->isVerwaltung() ? $alle->only($user->scopeKeysFor('stufenadmin')) : $alle)->all();
    }

    public static function form(Schema $schema): Schema
    {
        $adresse = implode(', ', array_filter([config('dpsg.contact.street'), config('dpsg.contact.city')]));

        return $schema->components([
            Section::make()
                ->description('Erscheint auf der Startseite und auf der Stufenseite. Hat eine Stufe mehrere Gruppen, je Gruppe einen Eintrag mit Namen anlegen.')
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    Select::make('stage')->label('Stufe')->options(fn () => self::stufen())->required()->native(false),
                    TextInput::make('name')->label('Name der Gruppe')->maxLength(80)
                        ->helperText('Nur nötig, wenn die Stufe mehrere Gruppen hat, z. B. „Meute Akela“.'),
                    Select::make('wochentag')->label('Wochentag')->options(Gruppenstunde::WOCHENTAGE)->native(false),
                    TextInput::make('hinweis')->label('Hinweis')->maxLength(160)
                        ->helperText('z. B. „14-tägig“ oder „nicht in den Schulferien“'),
                    TimePicker::make('beginn')->label('Beginn')->seconds(false),
                    TimePicker::make('ende')->label('Ende')->seconds(false)->after('beginn'),
                    TextInput::make('ort')->label('Ort')->maxLength(160)->columnSpanFull()
                        ->placeholder($adresse)
                        ->helperText('Leer lassen: Adresse des Stamms.'),
                    TextInput::make('sort')->label('Reihenfolge innerhalb der Stufe')->numeric()->default(0),
                    Toggle::make('active')->label('Anzeigen')->default(true)->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('stage')->label('Stufe')
                    ->formatStateUsing(fn (string $state) => config("dpsg.stages.{$state}.label", $state))->weight('bold'),
                TextColumn::make('name')->label('Gruppe')->placeholder('–'),
                TextColumn::make('zeit')->label('Wann')->state(fn (Gruppenstunde $record) => $record->zeitText() ?: $record->hinweis),
                TextColumn::make('ort')->label('Ort')->placeholder('Adresse des Stamms')->wrap(),
                IconColumn::make('active')->label('Aktiv')->boolean(),
            ])
            ->filters([SelectFilter::make('stage')->label('Stufe')->options(fn () => self::stufen())])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGruppenstunden::route('/'),
            'create' => CreateGruppenstunde::route('/create'),
            'edit' => EditGruppenstunde::route('/{record}/edit'),
        ];
    }
}
