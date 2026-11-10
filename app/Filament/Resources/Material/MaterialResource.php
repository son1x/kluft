<?php

namespace App\Filament\Resources\Material;

use App\Filament\Resources\Material\Pages\BelegungMaterial;
use App\Filament\Resources\Material\Pages\CreateMaterial;
use App\Filament\Resources\Material\Pages\EditMaterial;
use App\Filament\Resources\Material\Pages\ListMaterial;
use App\Models\Material;
use App\Support\Materialverleih;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Materialverleih (docs/materialverleih.md): was es gibt, wie viel, wo.
 */
class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Materialverleih';

    protected static ?string $slug = 'material';

    protected static ?string $navigationLabel = 'Material';

    protected static ?string $modelLabel = 'Material';

    protected static ?string $pluralModelLabel = 'Material';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return Materialverleih::zugriff(Filament::auth()->user());
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

    /** @return list<string> */
    public static function kategorien(): array
    {
        return Material::query()->whereNotNull('kategorie')->distinct()->orderBy('kategorie')->pluck('kategorie')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->components([
                TextInput::make('name')->label('Name')->required()->maxLength(150)->placeholder('z. B. Jurte 6 m, Kohtenkreuz, Beamer'),
                TextInput::make('kategorie')->label('Kategorie')->maxLength(80)->placeholder('z. B. Zelte, Technik, Küche')
                    ->datalist(fn () => self::kategorien()),
                TextInput::make('menge')->label('Anzahl')->integer()->minValue(1)->maxValue(999)->default(1)->required()
                    ->helperText('Wie viele Stück gibt es? Die Belegung wird dagegen geprüft.'),
                TextInput::make('ort')->label('Lagerort')->maxLength(150)->placeholder('z. B. Keller Diözesanzentrum, Regal 3'),
                Textarea::make('beschreibung')->label('Beschreibung')->rows(3)->maxLength(2000)->columnSpanFull()
                    ->helperText('Erscheint auf der Website, wenn Anfragen über die Website eingeschaltet sind.'),
                Textarea::make('hinweise')->label('Interne Hinweise')->rows(2)->maxLength(2000)->columnSpanFull()
                    ->helperText('Nur im Backend, z. B. Zubehör, Pflege, Schlüssel.'),
                FileUpload::make('bild')->label('Bild')->image()->imageEditor()->maxSize(5120)
                    ->disk('public')->directory('media/material')->visibility('public'),
                Toggle::make('aktiv')->label('Kann ausgeliehen werden')->default(true)->inline(false)
                    ->helperText('Aus: z. B. kaputt oder ausgemustert; die Belegung bleibt erhalten.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['reservierungen as offen_count' => fn ($q) => $q->where('status', 'angefragt')]))
            ->columns([
                ImageColumn::make('bild')->label('')->disk('public')->square()->size(48)->visibleFrom('sm'),
                TextColumn::make('name')->label('Name')->searchable()->sortable()->weight('bold')
                    ->description(fn (Material $record) => $record->kategorie),
                TextColumn::make('menge')->label('Anzahl')->alignEnd(),
                TextColumn::make('heute')->label('Heute frei')->alignEnd()
                    ->state(fn (Material $record) => $record->frei(today(), today()).' / '.$record->menge)
                    ->color(fn (Material $record) => $record->frei(today(), today()) === 0 ? 'danger' : null),
                TextColumn::make('offen_count')->label('Anfragen')->badge()->color('info')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? (string) $state : '')
                    ->visibleFrom('md'),
                TextColumn::make('ort')->label('Lagerort')->toggleable()->visibleFrom('lg')->placeholder('–'),
            ])
            ->filters([
                SelectFilter::make('kategorie')->label('Kategorie')->options(fn () => array_combine(self::kategorien(), self::kategorien())),
                SelectFilter::make('aktiv')->label('Verleihbar')->options(['1' => 'Ja', '0' => 'Nein'])->default('1'),
            ])
            ->recordActions([
                Action::make('belegung')->label('Belegung')->icon(Heroicon::OutlinedCalendarDays)
                    ->url(fn (Material $record) => static::getUrl('belegung', ['record' => $record])),
                EditAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCube)
            ->emptyStateHeading('Noch kein Material')
            ->emptyStateDescription('Legt Zelte, Technik oder Küchenkisten an, die ihr verleiht. Danach lassen sie sich reservieren.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterial::route('/'),
            'create' => CreateMaterial::route('/neu'),
            'edit' => EditMaterial::route('/{record}/bearbeiten'),
            'belegung' => BelegungMaterial::route('/{record}/belegung'),
        ];
    }

    /** @return array<int, string> */
    public static function auswahl(): array
    {
        return Material::query()->orderBy('name')->get()
            ->mapWithKeys(fn (Material $m) => [$m->id => $m->name.($m->menge > 1 ? ' ('.$m->menge.' Stück)' : '').($m->aktiv ? '' : ' – nicht verleihbar')])->all();
    }
}
