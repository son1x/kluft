<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\Pages\ManageSets;
use App\Models\Shop\Artikel;
use App\Models\Shop\Set;
use App\Models\Shop\SetTeil;
use App\Support\Sammelbestellung;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Artikelsets (SB-38), z. B. „Starterset“ aus Kluft, Aufnähern und Knoten.
 * Optionale Teile hakt die bestellende Person an oder ab.
 */
class SetResource extends Resource
{
    use Zugriff;

    protected static ?string $model = Set::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static string|UnitEnum|null $navigationGroup = 'Sammelbestellung';

    protected static ?string $modelLabel = 'Set';

    protected static ?string $pluralModelLabel = 'Sets';

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        $artikel = fn () => Artikel::query()->orderBy('name')->get()->mapWithKeys(fn (Artikel $a) => [$a->id => $a->name.($a->freigegeben ? '' : ' (nicht im Shop)')])->all();

        return $schema->columns(2)->components([
            TextInput::make('name')->label('Name')->required()->maxLength(120)->placeholder('z. B. Starterset'),
            Select::make('bild_artikel_id')->label('Bild von')->options($artikel)->helperText('Das Set zeigt im Angebot das Bild dieses Artikels.'),
            Textarea::make('beschreibung')->label('Beschreibung')->rows(3)->maxLength(2000)->columnSpanFull()
                ->placeholder('z. B. Alles für den Anfang: Kluft, Stammesaufnäher und Halstuchknoten. Das Halstuch bekommst du bei uns zum Versprechen.'),
            Toggle::make('freigegeben')->label('Im Shop anbieten')->inline(false),
            TextInput::make('sort')->label('Reihenfolge')->numeric()->default(0),
            Repeater::make('teile')->relationship()->label('Teile')->columnSpanFull()->columns(4)->orderColumn('sort')->minItems(1)->defaultItems(1)
                ->addActionLabel('Teil hinzufügen')
                ->itemLabel(fn (array $state) => isset($state['artikel_id']) ? Artikel::query()->whereKey($state['artikel_id'])->value('name') : null)
                ->schema([
                    Select::make('artikel_id')->label('Artikel')->options($artikel)->required()->searchable()->columnSpan(2),
                    TextInput::make('menge')->label('Anzahl')->numeric()->minValue(1)->maxValue(10)->default(1)->required(),
                    Toggle::make('optional')->label('optional')->inline(false)->live(),
                    Toggle::make('vorgewaehlt')->label('anfangs angehakt')->inline(false)->default(true)
                        ->visible(fn (Get $get) => (bool) $get('optional'))
                        ->helperText('Aus, wenn z. B. der Stamm das Halstuch selbst ausgibt.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->modifyQueryUsing(fn ($query) => $query->with('teile.artikel.varianten'))
            ->emptyStateHeading('Noch keine Sets')
            ->emptyStateDescription('Z. B. ein „Starterset“ aus Kluft, Aufnähern und Knoten. Die Artikel gibst du vorher unter „Artikel“ frei.')
            ->columns([
                TextColumn::make('name')->label('Set')->weight('bold')
                    ->description(fn (Set $record) => $record->teile->map(fn (SetTeil $t) => ($t->menge > 1 ? $t->menge.' × ' : '').$t->artikel?->name.($t->optional ? ' (optional)' : ''))->implode(', ')),
                TextColumn::make('preis')->label('ab')->state(fn (Set $record) => Sammelbestellung::euro(Sammelbestellung::setPreisAb($record))),
                TextColumn::make('hinweis')->label('')->badge()->color('danger')
                    ->state(fn (Set $record) => $record->bestellbar() ? null : 'nicht bestellbar: Pflichtteil fehlt im Shop'),
                ToggleColumn::make('freigegeben')->label('Im Shop'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSets::route('/')];
    }
}
