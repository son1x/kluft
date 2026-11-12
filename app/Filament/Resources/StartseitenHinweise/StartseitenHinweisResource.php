<?php

namespace App\Filament\Resources\StartseitenHinweise;

use App\Filament\Resources\StartseitenHinweise\Pages\CreateStartseitenHinweis;
use App\Filament\Resources\StartseitenHinweise\Pages\EditStartseitenHinweis;
use App\Filament\Resources\StartseitenHinweise\Pages\ListStartseitenHinweise;
use App\Models\StartseitenHinweis;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Hinweise auf der Startseite: Kasten unter dem Titelbild, mehrere
 * untereinander (Reihenfolge per Ziehen), an/aus und mit Zeitraum.
 */
class StartseitenHinweisResource extends Resource
{
    protected static ?string $model = StartseitenHinweis::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Inhalte';

    protected static ?string $modelLabel = 'Startseiten-Hinweis';

    protected static ?string $pluralModelLabel = 'Startseiten-Hinweise';

    protected static ?string $slug = 'startseiten-hinweise';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description('Erscheint gut sichtbar direkt unter dem Titelbild der Startseite, mehrere Hinweise untereinander. Kurz halten: Titel, ein bis zwei Sätze und ein Link.')
                ->columns(2)->columnSpanFull()
                ->components([
                    TextInput::make('titel')->label('Titel')->required()->maxLength(120)->columnSpanFull()
                        ->placeholder('z. B. Aufarbeitung sexualisierter Gewalt in der DPSG'),
                    Textarea::make('text')->label('Text')->rows(3)->maxLength(400)->columnSpanFull()
                        ->helperText('Ein bis zwei Sätze, höchstens 400 Zeichen.'),
                    TextInput::make('link_url')->label('Link')->maxLength(500)->live(onBlur: true)
                        ->rule('regex:#^(https://|/)#')
                        ->validationMessages(['regex' => 'Bitte eine Adresse mit https:// oder einen Pfad dieser Website mit / am Anfang angeben.'])
                        ->placeholder('https://… oder /praevention')
                        ->helperText('Andere Websites öffnen in einem neuen Tab.'),
                    TextInput::make('link_text')->label('Beschriftung des Knopfs')->maxLength(80)
                        ->required(fn (Get $get) => filled($get('link_url')))
                        ->placeholder('z. B. Mehr erfahren'),
                    Select::make('farbe')->label('Farbe')->options(StartseitenHinweis::FARBEN)->default('rot')->required()->native(false)
                        ->selectablePlaceholder(false),
                    Toggle::make('aktiv')->label('Auf der Startseite zeigen')->default(true)->inline(false),
                    DatePicker::make('ab')->label('Zeigen ab')->helperText('Leer = sofort'),
                    DatePicker::make('bis')->label('Zeigen bis')->helperText('Leer = bis zum Ausschalten')->afterOrEqual('ab'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('titel')->label('Titel')->weight('bold')->wrap()
                    ->description(fn (StartseitenHinweis $record) => $record->link_url),
                TextColumn::make('farbe')->label('Farbe')->badge()
                    ->formatStateUsing(fn (string $state) => StartseitenHinweis::FARBEN[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'rot' => 'danger', 'blau' => 'info', default => 'gray'
                    }),
                IconColumn::make('aktiv')->label('An')->boolean(),
                TextColumn::make('zeitraum')->label('Zeitraum')
                    ->state(fn (StartseitenHinweis $record) => match (true) {
                        $record->ab && $record->bis => $record->ab->format('d.m.Y').' – '.$record->bis->format('d.m.Y'),
                        (bool) $record->ab => 'ab '.$record->ab->format('d.m.Y'),
                        (bool) $record->bis => 'bis '.$record->bis->format('d.m.Y'),
                        default => 'immer',
                    }),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->emptyStateHeading('Keine Hinweise')
            ->emptyStateDescription('Für wichtige Mitteilungen auf der Startseite, z. B. Aufarbeitung, Büro geschlossen oder Anmeldeschluss.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStartseitenHinweise::route('/'),
            'create' => CreateStartseitenHinweis::route('/create'),
            'edit' => EditStartseitenHinweis::route('/{record}/edit'),
        ];
    }
}
