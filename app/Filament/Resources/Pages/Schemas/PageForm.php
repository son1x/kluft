<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Page;
use App\Support\Aufrufe;
use App\Support\Features;
use App\Support\RoleCatalog;
use App\Support\Slug;
use App\Support\Websites;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
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
                    // Seitenaufrufe ohne Personenbezug (docs/aufrufe.md).
                    Placeholder::make('aufrufe')
                        ->hiddenLabel()
                        ->content(fn (?Page $record) => Aufrufe::angabe(self::pfad($record)))
                        ->visible(fn (?Page $record) => $record?->exists && Features::enabled(Features::AUFRUFE))
                        ->columnSpan(2),
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

                    Slug::feld(TextInput::make('slug'))
                        ->label('URL-Pfad (Slug)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        // Die Vorstandsseite hängt am Slug (config dpsg.gremien.leitung.page).
                        ->disabled(fn (?Page $record) => self::nurVorstandsseite($record))
                        ->helperText('Erreichbar unter /{slug}. Später ändern macht alte Links ungültig; dann unter „Weiterleitungen“ den alten Pfad auf den neuen umleiten.'),

                    Select::make('parent_id')
                        ->label('Übergeordnete Seite')
                        ->relationship('parent', 'title')
                        ->disabled(fn (?Page $record) => self::nurVorstandsseite($record))
                        ->searchable()
                        ->preload()
                        ->helperText('Macht diese Seite zur Unterseite, z. B. „Wölflinge“ unter „Die Stufen“. Zusammen mit „In Hauptnavigation anzeigen“ wird sie im Menü ein Unterpunkt.'),

                    Select::make('stage')
                        ->label('Stufe')
                        ->options(fn () => self::stageOptions())
                        ->native(false)
                        ->visible(fn () => self::stageOptions() !== [])
                        ->required(fn (Get $get, ?Page $record) => ! self::isGlobal() && ! self::nurVorstandsseite($record) && self::sectionOptions() === [])
                        ->helperText('Nur setzen, wenn diese Seite zu einer bestimmten Stufe gehört.'),

                    Select::make('section')
                        ->label('Fachbereich / Website')
                        ->options(fn () => self::sectionOptions())
                        ->native(false)
                        ->visible(fn () => self::sectionOptions() !== [])
                        ->required(fn (Get $get, ?Page $record) => ! self::isGlobal() && ! self::nurVorstandsseite($record) && blank($get('stage')))
                        ->helperText('Seiten eines Fachbereichs erscheinen in dessen Navigation. Fachbereiche mit eigener Domain (in Klammern) sind zugleich eine eigene Website.'),

                    Select::make('status')
                        ->label('Status')
                        ->options(fn (?Page $record) => self::statusOptions($record))
                        ->default('draft')
                        ->native(false)
                        ->required()
                        ->helperText(fn (?Page $record) => array_key_exists('published', self::statusOptions($record))
                            ? 'Entwurf: nur im Backend sichtbar. Veröffentlicht: auf der Website.'
                            : 'Entwurf: nur im Backend sichtbar. Zur Prüfung: Die Verwaltung schaut drüber und veröffentlicht die Seite.'),

                    Toggle::make('show_in_nav')
                        ->label('In Hauptnavigation anzeigen')
                        ->helperText('Mit übergeordneter Seite erscheint sie als Unterpunkt (z. B. unter „Die Stufen“ oder „Fachbereiche“).'),

                    TextInput::make('nav_sort')
                        ->label('Reihenfolge in der Navigation')
                        ->numeric()
                        ->default(0)
                        ->helperText('Kleinere Zahl steht weiter vorn. In Zehnerschritten (10, 20, 30) lässt sich später etwas dazwischenschieben.'),
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

            // Auf der Startseite ist die SEO-Beschreibung zugleich der Slogan
            // auf dem Titelbild, daher dort aufgeklappt und erklärt.
            Section::make('SEO')
                ->collapsed(fn (Get $get) => $get('slug') !== 'start')
                ->components([
                    TextInput::make('seo_title')
                        ->label('SEO-Titel')
                        ->maxLength(255),
                    Textarea::make('seo_description')
                        ->label('SEO-Beschreibung')
                        ->maxLength(500)
                        ->helperText(fn (Get $get) => $get('slug') === 'start'
                            ? 'Erscheint als Slogan unter dem Titel auf dem Titelbild der Startseite und als Beschreibung in Suchmaschinen. Am besten ein kurzer Satz.'
                            : null),
                ]),
        ]);
    }

    private static function isGlobal(): bool
    {
        return (bool) Filament::auth()->user()?->darfUeberall('seiten');
    }

    /** Vorstand ohne Verwaltungsrechte bearbeitet eine Vorstandsseite. */
    private static function nurVorstandsseite(?Page $record): bool
    {
        return ! self::isGlobal() && (bool) Filament::auth()->user()?->darf('vorstandsseiten') && (bool) $record?->istVorstandsseite();
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
            : $all->only(Filament::auth()->user()?->bereicheFuer('seiten', 'stage') ?? [])->all();
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
            : $all->only(Filament::auth()->user()?->bereicheFuer('seiten', 'section') ?? [])->all();
    }

    /**
     * Autoren/Stufenadmins ohne Verwaltungsrechte dürfen nicht direkt
     * veröffentlichen (Standard: Freigabe erforderlich, F-CMS-10).
     *
     * @return array<string, string>
     */
    private static function statusOptions(?Page $record = null): array
    {
        $options = [
            'draft' => 'Entwurf',
            'review' => 'Zur Prüfung',
        ];

        $user = Filament::auth()->user();

        // Stufen- und Fachbereichsleitungen veröffentlichen die Seiten ihres
        // Bereichs selbst (wie Beiträge, PostPolicy::publish); welche Seiten das
        // sind, regeln PagePolicy und PageResource. Vorstand: Vorstandsseiten
        // direkt veröffentlichen (wie News freigeben, 5.4).
        if ($user && ($user->darf('seiten') || self::nurVorstandsseite($record))) {
            $options['published'] = 'Veröffentlicht';
        }

        return $options;
    }

    /** Öffentlicher Pfad der Seite; die Seite „start“ ist die Startseite. */
    private static function pfad(?Page $record): string
    {
        return $record?->slug === 'start' ? '/' : '/'.$record?->slug;
    }
}
