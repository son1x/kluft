<?php

namespace App\Filament\Resources\Adressbuch;

use App\Filament\Resources\Adressbuch\Pages\CreateAdressbuchKontakt;
use App\Filament\Resources\Adressbuch\Pages\EditAdressbuchKontakt;
use App\Filament\Resources\Adressbuch\Pages\ListAdressbuchKontakte;
use App\Models\AdressbuchKontakt;
use App\Support\Adressbuch;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
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
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Kontakte im Adressbuch (docs/adressbuch.md): gemeinsam für alle mit Zugriff.
 */
class AdressbuchKontaktResource extends Resource
{
    protected static ?string $model = AdressbuchKontakt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Verband & Kontakt';

    protected static ?string $slug = 'adressbuch';

    protected static ?string $navigationLabel = 'Adressbuch';

    protected static ?string $modelLabel = 'Kontakt';

    protected static ?string $pluralModelLabel = 'Kontakte';

    protected static ?int $navigationSort = 32;

    public static function canViewAny(): bool
    {
        return Adressbuch::zugriff(Filament::auth()->user());
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

    /**
     * @return list<mixed>
     */
    public static function felder(): array
    {
        return [
            TextInput::make('name')->label('Name')->maxLength(150),
            TextInput::make('email')->label('E-Mail')->email()->required()->maxLength(255)
                ->unique(AdressbuchKontakt::class, 'email', ignoreRecord: true)
                ->dehydrateStateUsing(fn ($state) => mb_strtolower(trim((string) $state))),
            TextInput::make('organisation')->label('Organisation / Funktion')->maxLength(150)->placeholder('z. B. Stamm St. Georg, Bezirk Saar-Hochwald'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        $user = Filament::auth()->user();

        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->components([
                ...static::felder(),
                Select::make('verteiler')->label('Verteiler')
                    ->relationship('verteiler', 'name', fn (Builder $query) => $query->whereIn('adressbuch_verteiler.id', Adressbuch::sichtbareVerteiler($user)->select('id')))
                    ->multiple()->preload()->searchable(),
                Textarea::make('notiz')->label('Notiz')->rows(2)->maxLength(1000)->columnSpanFull()
                    ->helperText('Nur intern. Keine sensiblen Angaben.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $user = Filament::auth()->user();

        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['verteiler' => fn ($v) => $v->whereIn('adressbuch_verteiler.id', Adressbuch::sichtbareVerteiler($user)->select('id'))]))
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable()->placeholder('–'),
                TextColumn::make('email')->label('E-Mail')->searchable()->sortable()->copyable(),
                TextColumn::make('organisation')->label('Organisation')->searchable()->toggleable()->placeholder('–'),
                TextColumn::make('verteiler.name')->label('Verteiler')->badge()->placeholder('–'),
            ])
            ->filters([
                SelectFilter::make('in_verteiler')->label('Verteiler')
                    ->options(fn () => Adressbuch::sichtbareVerteiler($user)->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->whereHas('verteiler', fn ($v) => $v->where('adressbuch_verteiler.id', $data['value'])) : $query),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Noch keine Kontakte')
            ->emptyStateDescription('Kontakte entstehen hier oder beim „Adressen einfügen“ in einem Verteiler.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdressbuchKontakte::route('/'),
            'create' => CreateAdressbuchKontakt::route('/neu'),
            'edit' => EditAdressbuchKontakt::route('/{record}/bearbeiten'),
        ];
    }
}
