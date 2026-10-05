<?php

namespace App\Filament\Resources\People;

use App\Filament\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Models\Media;
use App\Models\Person;
use App\Support\Site;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
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
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Personenvorstellung auf den Seiten der Gremien (Diözesanleitung,
 * Diözesanbüro).
 */
class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Verband & Kontakt';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Person';

    protected static ?string $pluralModelLabel = 'Personen';

    protected static ?string $slug = 'personen';

    /**
     * Hochgeladenes Porträt als Mediathek-Eintrag anlegen und zuordnen
     * (aus Create-/EditPerson aufgerufen). Rechte gelten als bestätigt,
     * wenn die Veröffentlichung mit der Person abgestimmt ist.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function storePortrait(array $data): array
    {
        $path = $data['portrait_upload'] ?? null;
        $credit = $data['portrait_credit'] ?? null;
        unset($data['portrait_upload'], $data['portrait_credit']);

        if (is_array($path)) {
            $path = array_values($path)[0] ?? null;
        }
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            return $data;
        }

        $media = Media::query()->create([
            'path' => $path,
            'mime' => Storage::disk('public')->mimeType($path) ?: 'image/jpeg',
            'size' => Storage::disk('public')->size($path),
            'alt_text' => 'Porträt '.($data['name'] ?? ''),
            'credit' => (string) $credit,
            'rights_confirmed' => (bool) ($data['consent'] ?? false),
            'uploaded_by' => auth()->id(),
        ]);
        $data['photo_media_id'] = $media->id;

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $gremien = collect(config('dpsg.gremien'))->map(fn (array $g) => $g['label'])->all();

        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    TextInput::make('name')->label('Name')->required()->maxLength(150)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set, Get $get) => blank($get('email')) && ! in_array($get('gremium'), ['dak', 'fak'], true)
                            ? $set('email', Person::suggestedEmail($state)) : null),
                    TextInput::make('role')->label('Funktion')->placeholder('z. B. Referentin der Wölflingsstufe')->maxLength(150),
                    Select::make('gremium')->label('Gremium')->options($gremien)->in(array_keys($gremien))->required()->native(false)
                        ->live()
                        ->helperText('Bestimmt, auf welcher Seite die Person erscheint. Mitglieder im '.Site::akShort().' erscheinen auf der Seite ihrer Stufe.'),
                    Select::make('stage')->label('Stufe')
                        ->options(collect(config('dpsg.stages'))->map(fn (array $s) => $s['label'])->all())
                        ->in(array_keys(config('dpsg.stages')))
                        ->placeholder('–')
                        ->native(false)
                        ->required(fn (Get $get) => $get('gremium') === 'dak')
                        ->helperText('Für Stufenreferent*innen und Mitglieder im '.Site::akShort().': Sie erscheinen dann auf der Stufenseite.'),
                    TextInput::make('subgroup')->label('Zwischenüberschrift')
                        ->placeholder('z. B. '.config('dpsg.site.leitung_label'))
                        ->datalist(fn () => Person::query()->whereNotNull('subgroup')->distinct()->orderBy('subgroup')->pluck('subgroup')->all())
                        ->maxLength(100),
                    Select::make('badge')->label('Gestaltung des Initialen-Kreises')
                        ->options(config('dpsg.person_badges'))
                        ->in(array_keys(config('dpsg.person_badges')))
                        ->placeholder('DPSG-Blau')
                        ->native(false),
                    Select::make('section')->label(fn (Get $get) => $get('gremium') === 'fak' ? 'Fachbereich' : 'Ansprechperson für Fachbereich')
                        ->required(fn (Get $get) => $get('gremium') === 'fak')
                        ->options(collect(config('dpsg.sections'))->map(fn (array $s) => $s['label'])->all())
                        ->placeholder('–')
                        ->native(false)
                        ->helperText(fn (Get $get) => $get('gremium') === 'fak'
                            ? 'Mitglieder im Facharbeitskreis erscheinen auf der Seite ihres Fachbereichs (ohne eigene Adresse).'
                            : 'Erscheint dann auf der Startseite des Fachbereichs als Ansprechperson.'),
                    TextInput::make('email')->label('E-Mail')->email()->maxLength(255)
                        ->visible(fn (Get $get) => ! in_array($get('gremium'), ['dak', 'fak'], true))
                        ->placeholder(fn (Get $get) => Person::suggestedEmail($get('name')) ?? 'initial.nachname@'.config('dpsg.person_email_domain'))
                        ->helperText('Standard: Initial.Nachname@'.config('dpsg.person_email_domain').' – leer lassen, dann wird diese Adresse angezeigt. Mitglieder im '.Site::akShort().' haben keine eigene Adresse.'),
                    Textarea::make('bio')->label('Vorstellungstext')->rows(6)->maxLength(3000)->columnSpanFull()
                        ->helperText('Absätze mit einer Leerzeile trennen. Lange Texte werden mit „Mehr lesen“ eingeklappt.'),
                    TextInput::make('sort')->label('Reihenfolge')->numeric()->default(0),
                    Toggle::make('consent')->label('Veröffentlichung mit der Person abgestimmt')
                        ->helperText('Name, Funktion, Text und ggf. Foto erscheinen erst, wenn die Person zugestimmt hat (KDG).'),
                    Toggle::make('active')->label('Aktiv')->default(true),
                ]),

            // Porträt wie bei dpsg-koeln.de: direkt hier hochladen, quadratisch
            // zuschneiden; landet automatisch in der Mediathek.
            Section::make('Porträtfoto')
                ->description('Ohne Foto erscheint ein Kreis mit den Initialen. Das Foto wird rund angezeigt – beim Hochladen auf einen quadratischen Ausschnitt mit dem Gesicht in der Mitte zuschneiden.')
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    Placeholder::make('current_photo')->label('Aktuelles Foto')
                        ->visible(fn (?Person $record) => $record?->photo !== null)
                        ->content(fn (?Person $record) => new HtmlString('<img src="'.e($record?->photo?->url()).'" alt="" style="width:96px;height:96px;border-radius:9999px;object-fit:cover">')),
                    FileUpload::make('portrait_upload')->label(fn (?Person $record) => $record?->photo ? 'Neues Foto hochladen' : 'Foto hochladen')
                        ->image()
                        ->disk('public')->directory('media/portraits')->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->imageEditor()
                        ->imageEditorAspectRatios(['1:1'])
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeTargetWidth('800')
                        ->imageResizeTargetHeight('800')
                        ->imageResizeMode('cover')
                        ->live(),
                    TextInput::make('portrait_credit')->label('Bildnachweis (Fotograf*in)')
                        ->maxLength(150)
                        ->required(fn (Get $get) => filled($get('portrait_upload')))
                        ->visible(fn (Get $get) => filled($get('portrait_upload'))),
                    Select::make('photo_media_id')->label('… oder Bild aus der Mediathek wählen')
                        ->relationship('photo', 'alt_text')->searchable()->preload()
                        ->hidden(fn (Get $get) => filled($get('portrait_upload'))),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('role')->label('Funktion')->searchable(),
                TextColumn::make('gremium')->label('Gremium')->badge()
                    ->formatStateUsing(fn (string $state) => config("dpsg.gremien.{$state}.label", $state)),
                TextColumn::make('subgroup')->label('Zwischenüberschrift')->toggleable(),
                IconColumn::make('consent')->label('Abgestimmt')->boolean(),
                IconColumn::make('active')->label('Aktiv')->boolean(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters([
                SelectFilter::make('gremium')->label('Gremium')->options(collect(config('dpsg.gremien'))->map(fn (array $g) => $g['label'])->all()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'create' => CreatePerson::route('/create'),
            'edit' => EditPerson::route('/{record}/edit'),
        ];
    }
}
