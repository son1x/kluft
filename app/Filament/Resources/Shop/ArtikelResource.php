<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\Pages\EditArtikel;
use App\Filament\Resources\Shop\Pages\ListArtikel;
use App\Models\Shop\Artikel;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnitEnum;

/**
 * Artikel der Sammelbestellung: per Rüsthaus-Nummer holen (Bild, Beschreibung,
 * Größen werden gespeichert) und freigeben (SB-10 bis SB-13).
 */
class ArtikelResource extends Resource
{
    use Zugriff;

    protected static ?string $model = Artikel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Sammelbestellung';

    protected static ?string $modelLabel = 'Artikel';

    protected static ?string $pluralModelLabel = 'Artikel';

    protected static ?int $navigationSort = 20;

    /** Angelegt werden nur eigene Artikel; Rüsthaus-Artikel kommen über „Artikel holen“. */
    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function euroFeld(TextInput $feld): TextInput
    {
        return $feld->suffix('€')->inputMode('decimal')->rules(['regex:/^\d{1,5}([.,]\d{1,2})?$/'])
            ->formatStateUsing(fn ($state) => $state === null ? null : number_format($state / 100, 2, ',', ''))
            ->dehydrateStateUsing(fn ($state) => (int) round((float) str_replace(',', '.', (string) $state) * 100));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->components([
                TextInput::make('name')->label('Bezeichnung im Shop')->required()->maxLength(160),
                TextInput::make('kategorie')->label('Kategorie im Shop')->maxLength(80)->placeholder('z. B. Kluft, Halstuch, Aufnäher'),
                Textarea::make('beschreibung')->label('Beschreibung')->rows(5)->maxLength(3000)->columnSpanFull(),
                Select::make('bild_media_id')->label('Bild')->relationship('bild', 'alt_text')->searchable()->preload()
                    ->visible(fn (?Artikel $record) => $record === null || $record->eigen)
                    ->helperText('Aus der Mediathek; ein neues Bild dort zuerst hochladen.'),
                Toggle::make('freigegeben')->label('Im Shop anbieten')->inline(false)->default(true),
                TextInput::make('sort')->label('Reihenfolge')->numeric()->default(0),
                Repeater::make('varianten')->relationship()->label('Größen / Ausführungen')->columnSpanFull()->columns(4)
                    ->addable(fn (?Artikel $record) => $record === null || $record->eigen)
                    ->deletable(fn (?Artikel $record) => $record === null || $record->eigen)
                    ->reorderable(false)->minItems(1)->defaultItems(1)->addActionLabel('Größe hinzufügen')
                    ->helperText(fn (?Artikel $record) => $record === null || $record->eigen
                        ? 'Ohne Größen: eine Zeile ohne Bezeichnung. Mit Größen: je Größe eine Zeile, z. B. S, M, L.'
                        : 'Größen und Nummern kommen vom Rüsthaus („Aktualisieren“). Hier nur Bezeichnung, Preis und Anbieten änderbar.')
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => $data + ['nummer' => 'V-'.Str::upper(Str::random(8))])
                    ->schema([
                        TextInput::make('bezeichnung')->label('Bezeichnung')->maxLength(120)->columnSpan(2)->placeholder('z. B. Größe M'),
                        self::euroFeld(TextInput::make('preis_cent')->label('Preis'))->required(),
                        Toggle::make('aktiv')->label('anbieten')->inline(false)->default(true),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->emptyStateHeading('Noch keine Artikel')
            ->emptyStateDescription('Oben „Artikel holen“: Rüsthaus-Artikelnummer oder Link eingeben.')
            ->columns([
                TextColumn::make('name')->label('Artikel')->weight('bold')->searchable()
                    ->description(fn (Artikel $record) => ($record->eigen ? 'Eigener Artikel' : 'Nr. '.$record->nummer).($record->kategorie ? ' · '.$record->kategorie : '')),
                TextColumn::make('preis')->label('Preis')
                    ->state(fn (Artikel $record) => Sammelbestellung::euro($record->varianten()->where('aktiv', true)->min('preis_cent') ?? $record->preis_cent)),
                TextColumn::make('varianten_count')->counts('varianten')->label('Größen'),
                ToggleColumn::make('freigegeben')->label('Im Shop'),
                TextColumn::make('abgerufen_am')->label('Vom Rüsthaus')->since()->placeholder('–')
                    ->formatStateUsing(fn (Artikel $record, $state) => $record->eigen ? 'eigener Artikel' : $state),
            ])
            ->recordActions([
                Action::make('aktualisieren')->label('Aktualisieren')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (Artikel $record) => ! $record->eigen)
                    ->requiresConfirmation()->modalDescription('Name, Beschreibung, Preise und Größen neu vom Rüsthaus holen. Geänderte Preise gelten für neue Bestellungen.')
                    ->action(function (Artikel $record) {
                        try {
                            Sammelbestellung::freigeben($record->url ?: $record->nummer, $record->freigegeben);
                            AuditLogger::log('sammelbestellung.artikel.aktualisiert', Filament::auth()->user(), 'shop_artikel', $record->id, request());
                            Notification::make()->title('Aktualisiert')->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListArtikel::route('/'), 'edit' => EditArtikel::route('/{record}/edit')];
    }
}
