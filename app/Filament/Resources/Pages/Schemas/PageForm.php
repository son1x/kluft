<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Support\RoleCatalog;
use App\Support\Websites;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->components([
                    TextInput::make('title')
                        ->label('Titel')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set(
                            'slug',
                            str($state)->slug()
                        ))
                        ->columnSpan(2),

                    TextInput::make('slug')
                        ->label('URL-Pfad (Slug)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->helperText('Erreichbar unter /{slug}'),

                    Select::make('parent_id')
                        ->label('Übergeordnete Seite')
                        ->relationship('parent', 'title')
                        ->searchable()
                        ->preload(),

                    Select::make('stage')
                        ->label('Stufe')
                        ->options(fn () => self::stageOptions())
                        ->native(false)
                        ->visible(fn () => self::stageOptions() !== [])
                        ->required(fn (Get $get) => ! self::isGlobal() && self::sectionOptions() === [])
                        ->helperText('Nur setzen, wenn diese Seite zu einer bestimmten Stufe gehört.'),

                    Select::make('section')
                        ->label('Fachbereich / Website')
                        ->options(fn () => self::sectionOptions())
                        ->native(false)
                        ->visible(fn () => self::sectionOptions() !== [])
                        ->required(fn (Get $get) => ! self::isGlobal() && blank($get('stage')))
                        ->helperText('Seiten eines Fachbereichs erscheinen in dessen Navigation. Fachbereiche mit eigener Domain (in Klammern) sind zugleich eine eigene Website.'),

                    Select::make('status')
                        ->label('Status')
                        ->options(self::statusOptions())
                        ->default('draft')
                        ->native(false)
                        ->required(),

                    Toggle::make('show_in_nav')
                        ->label('In Hauptnavigation anzeigen')
                        ->helperText('Mit übergeordneter Seite erscheint sie als Unterpunkt (z. B. unter „Die Stufen“ oder „Fachbereiche“).'),

                    TextInput::make('nav_sort')
                        ->label('Reihenfolge in der Navigation')
                        ->numeric()
                        ->default(0),
                ]),

            Section::make('Inhalt')
                ->components([
                    Select::make('cover_media_id')
                        ->label('Titelbild')
                        ->relationship('coverMedia', 'alt_text')
                        ->searchable()
                        ->preload()
                        ->helperText('Auf der Startseite (Slug "start") das große Hero-Bild; Bilder vorher in der Mediathek hochladen. Ist „Titelbild wechselt zufällig“ an (Verwaltung → Funktionen), wechselt es sich mit den Titelbildern der Website ab.'),
                    RichEditor::make('body')
                        ->label('Inhalt')
                        ->required()
                        ->helperText('Video oder Karte einbetten: Link auf YouTube, Vimeo, OpenStreetMap oder Google Maps allein in eine eigene Zeile setzen – auf der Website erscheint ein Platzhalter, der erst nach Klick lädt.')
                        ->columnSpanFull(),
                ]),

            Section::make('SEO')
                ->collapsed()
                ->components([
                    TextInput::make('seo_title')
                        ->label('SEO-Titel')
                        ->maxLength(255),
                    Textarea::make('seo_description')
                        ->label('SEO-Beschreibung')
                        ->maxLength(500),
                ]),
        ]);
    }

    private static function isGlobal(): bool
    {
        return (bool) Filament::auth()->user()?->isVerwaltung();
    }

    /**
     * Verwaltung: alle Stufen; Stufenadmins nur die eigenen.
     *
     * @return array<string, string>
     */
    private static function stageOptions(): array
    {
        $all = collect(config('dpsg.stages'))->map(fn (array $stage) => $stage['label']);

        return self::isGlobal()
            ? $all->all()
            : $all->only(Filament::auth()->user()?->scopeKeysFor('stufenadmin') ?? [])->all();
    }

    /**
     * @return array<string, string>
     */
    private static function sectionOptions(): array
    {
        // Mit Domain, wenn die Sektion eine eigene Website hat.
        $all = collect(RoleCatalog::sections())->map(fn (string $label, string $key) => Websites::domainFor($key) === Websites::mainDomain() ? $label : $label.' ('.Websites::domainFor($key).')');

        return self::isGlobal()
            ? $all->all()
            : $all->only(Filament::auth()->user()?->scopeKeysFor('sektionsadmin') ?? [])->all();
    }

    /**
     * Autoren/Stufenadmins ohne Verwaltungsrechte dürfen nicht direkt
     * veröffentlichen (Standard: Freigabe erforderlich, F-CMS-10).
     *
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [
            'draft' => 'Entwurf',
            'review' => 'Zur Prüfung',
        ];

        $user = Filament::auth()->user();

        if ($user && ($user->isVerwaltung())) {
            $options['published'] = 'Veröffentlicht';
        }

        return $options;
    }
}
