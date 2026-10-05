<?php

namespace App\Filament\Resources\Downloads;

use App\Filament\Resources\Downloads\Pages\CreateDownload;
use App\Filament\Resources\Downloads\Pages\EditDownload;
use App\Filament\Resources\Downloads\Pages\ListDownloads;
use App\Models\Download;
use App\Support\Features;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Download-Seite: Methoden, Formulare, Arbeitshilfen. Typ, Größe, Seiten
 * und Stand ermittelt das CMS aus der Datei (App\Models\Download).
 */
class DownloadResource extends Resource
{
    protected static ?string $model = Download::class;

    // Nur in Instanzen mit Download-Seite (config dpsg.features).
    public static function isDiscovered(): bool
    {
        return Features::enabled(Features::DOWNLOADS);
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Inhalte';

    protected static ?string $modelLabel = 'Download';

    protected static ?string $pluralModelLabel = 'Downloads';

    protected static ?int $navigationSort = 40;

    /** MIME-Typen zu Download::EXTENSIONS (Browser-Auswahl und Prüfung). */
    private const MIMES = [
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.oasis.opendocument.text',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.oasis.opendocument.presentation',
        'application/zip', 'application/x-zip-compressed',
        'image/jpeg', 'image/png',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datei')
                ->description('Typ, Größe, Seitenzahl (PDF) bzw. Anzahl der Dateien (ZIP) und den Stand ermittelt das CMS selbst. Wird die Datei ersetzt, gilt sie 30 Tage als „neu“.')
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    FileUpload::make('file_path')
                        ->label('Datei')
                        ->disk(Download::DISK)
                        ->directory('downloads')
                        ->visibility('private')
                        ->acceptedFileTypes(self::MIMES)
                        ->rule('extensions:'.implode(',', Download::EXTENSIONS))
                        ->maxSize(51200)
                        ->storeFileNamesIn('file_name')
                        ->downloadable()
                        ->required()
                        ->helperText('PDF, Word, Excel, PowerPoint, OpenDocument, ZIP oder Bild, bis 50 MB.')
                        ->columnSpanFull(),
                    TextInput::make('title')->label('Titel')->required()->maxLength(120)
                        ->placeholder('z. B. Kennenlernspiele für die erste Gruppenstunde')
                        ->columnSpanFull(),
                    Textarea::make('description')->label('Kurzbeschreibung')->maxLength(300)->rows(2)
                        ->placeholder('Ein Satz: Was steckt drin, für wen ist es gedacht?')
                        ->columnSpanFull(),
                ]),

            Section::make('Einordnung')
                ->columns(4)
                ->columnSpanFull()
                ->components([
                    Select::make('category')->label('Kategorie')
                        ->options(Download::categories())
                        ->required()->native(false),
                    Select::make('stage')->label('Stufe (optional)')
                        ->options(collect(config('dpsg.stages'))->map(fn (array $s) => $s['label']))
                        ->native(false),
                    Select::make('section')->label('Fachbereich (optional)')
                        ->options(collect(config('dpsg.sections'))->map(fn (array $s) => $s['label']))
                        ->visible(fn () => Features::sections())
                        ->native(false),
                    TextInput::make('sort')->label('Reihenfolge')->numeric()->default(0)->minValue(0)
                        ->helperText('Kleinere Zahl steht weiter oben.'),
                ]),

            Section::make('Veröffentlichung')
                ->description('Nur Dateien veröffentlichen, die wir weitergeben dürfen und die keine personenbezogenen Daten enthalten (keine ausgefüllten Listen, keine Fotos ohne Einwilligung).')
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    Checkbox::make('rights_confirmed')
                        ->label('Rechte geklärt, keine personenbezogenen Daten in der Datei')
                        ->columnSpanFull(),
                    Toggle::make('published')
                        ->label('Auf der Download-Seite zeigen')
                        ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                            if ($value && ! $get('rights_confirmed')) {
                                $fail('Erst veröffentlichen, wenn Rechte und Datenschutz geklärt sind.');
                            }
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->defaultGroup('category')
            ->groups([
                Group::make('category')->label('Kategorie')
                    ->getTitleFromRecordUsing(fn (Download $record) => $record->categoryLabel()),
            ])
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->weight('bold')
                    ->description(fn (Download $record) => $record->description),
                TextColumn::make('typ')->label('Datei')->state(fn (Download $record) => $record->badge())
                    ->description(fn (Download $record) => $record->meta()),
                IconColumn::make('published')->label('Sichtbar')->boolean(),
                TextColumn::make('downloads')->label('Abrufe')->numeric()->sortable(),
                TextColumn::make('sort')->label('Reihenfolge')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')->label('Kategorie')->options(Download::categories()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDownloads::route('/'),
            'create' => CreateDownload::route('/create'),
            'edit' => EditDownload::route('/{record}/edit'),
        ];
    }
}
