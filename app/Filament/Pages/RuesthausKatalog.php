<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Redaktion\KatalogBildController;
use App\Jobs\ArtikelHolen;
use App\Models\Shop\Artikel;
use App\Models\Shop\Sperre;
use App\Models\User;
use App\Services\Sammelbestellung\Ruesthaus;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RuntimeException;
use UnitEnum;

/**
 * Sammelbestellung → Rüsthaus-Katalog (docs/pflichtenheft-sammelbestellung-shop.md,
 * Kapitel 6): Kategorie des Rüsthaus wählen, Artikel der Übersichtsseiten
 * ansehen (Nummer, Name, Preis, Vorschaubild über Kluft) und ausgewählte zur
 * Übernahme geben. Übernommen wird wie bei „Mehrere Artikel holen“ einzeln im
 * Hintergrund (ArtikelHolen), erst dann kommen Größen, Beschreibung und Bild.
 * Im öffentlichen Shop erscheint nur, was freigegeben ist.
 *
 * @property Schema $form
 */
class RuesthausKatalog extends Page
{
    protected string $view = 'filament.pages.ruesthaus-katalog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Sammelbestellung';

    protected static ?string $navigationLabel = 'Rüsthaus-Katalog';

    protected static ?string $title = 'Rüsthaus-Katalog';

    protected static ?string $slug = 'sammelbestellung/katalog';

    protected static ?int $navigationSort = 21;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var list<array{nummer: string, name: string, url: string, preis_cent: int, ab: bool, bild: ?string, kategorie: ?string}> */
    public array $artikel = [];

    /** @var list<string> Adressen der ausgewählten Artikel */
    public array $auswahl = [];

    public ?string $fehler = null;

    public bool $unvollstaendig = false;

    public static function canAccess(): bool
    {
        return Sammelbestellung::zugriff(Filament::auth()->user());
    }

    public function mount(): void
    {
        $this->form->fill(['nur_neue' => true, 'freigeben' => false]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Kategorie wählen')
                ->description('Die Liste kommt aus den Übersichtsseiten des Rüsthaus (eine Seite pro Sekunde, einen Tag zwischengespeichert). Gesperrte Kategorien stehen nicht zur Wahl.')
                ->columns(2)
                ->components([
                    Select::make('haupt')->label('Kategorie')
                        ->options(fn () => self::ohneGesperrte(app(Ruesthaus::class)->kategorien()))
                        ->placeholder('Bitte wählen')->native(false)->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('unter', null);
                            $this->leeren();
                        }),
                    Select::make('unter')->label('Unterkategorie (optional)')
                        ->options(fn (Get $get) => filled($get('haupt')) ? app(Ruesthaus::class)->unterkategorien((string) $get('haupt')) : [])
                        ->placeholder('alle Artikel der Kategorie')->native(false)->live()
                        ->visible(fn (Get $get) => filled($get('haupt')))
                        ->afterStateUpdated(fn () => $this->leeren()),
                ]),
            Section::make('Übernahme')
                ->columns(3)
                ->visible(fn () => $this->artikel !== [])
                ->components([
                    TextInput::make('suche')->label('Liste durchsuchen')->placeholder('Name oder Nummer')->live(debounce: 300),
                    Toggle::make('nur_neue')->label('Nur neue Artikel zeigen')->inline(false)->live(),
                    Toggle::make('freigeben')->label('Gleich im Shop zeigen')->inline(false)
                        ->helperText('Aus: kommen als Entwurf, „Im Shop“ schaltest du unter Artikel ein.'),
                    TextInput::make('shop_kategorie')->label('Kategorie im Shop (optional)')->maxLength(80)
                        ->placeholder('z. B. Kluft, Halstücher, Aufnäher')
                        ->datalist(fn () => Artikel::query()->whereNotNull('kategorie')->distinct()->orderBy('kategorie')->pluck('kategorie')->all())
                        ->helperText('Wird nur bei Artikeln ohne Kategorie gesetzt.'),
                ]),
        ]);
    }

    /** Artikel der gewählten (Unter-)Kategorie laden. */
    public function laden(bool $neu = false): void
    {
        abort_unless(static::canAccess(), 403);
        $url = (string) ($this->data['unter'] ?? '') ?: (string) ($this->data['haupt'] ?? '');
        if ($url === '') {
            return;
        }
        $this->leeren();
        if ($neu) {
            cache()->forget('ruesthaus3:liste:10:'.sha1($url));
        }
        try {
            $liste = app(Ruesthaus::class)->liste($url);
        } catch (RuntimeException $e) {
            $this->fehler = $e->getMessage();

            return;
        }
        if ($liste === null || $liste['artikel'] === []) {
            $this->fehler = 'In dieser Kategorie wurden keine Artikel erkannt. Vielleicht hat sich die Seite des Rüsthaus geändert; einzelne Artikel gehen weiter über „Artikel holen“.';

            return;
        }
        $this->artikel = $liste['artikel'];
        $this->unvollstaendig = $liste['unvollstaendig'];
    }

    /**
     * Zeilen für die Liste mit Status, gefiltert nach Suche und „nur neue“.
     *
     * @return list<array{nummer: string, name: string, url: string, preis: string, bild: ?string, status: string, waehlbar: bool}>
     */
    public function zeilen(): array
    {
        $vorhanden = Artikel::query()->where('eigen', false)->get(['nummer', 'url', 'freigegeben']);
        $q = mb_strtolower(trim((string) ($this->data['suche'] ?? '')));
        $zeilen = [];
        foreach ($this->artikel as $a) {
            $da = $vorhanden->first(fn (Artikel $v) => $v->url === $a['url'] || $v->nummer === $a['nummer']);
            $status = match (true) {
                Sperre::greift($a['nummer'], $a['kategorie']) => 'gesperrt',
                $da?->freigegeben === true => 'im Shop',
                $da !== null => 'Entwurf',
                default => 'neu',
            };
            if (($this->data['nur_neue'] ?? false) && $status !== 'neu') {
                continue;
            }
            if ($q !== '' && ! str_contains(mb_strtolower($a['name'].' '.$a['nummer']), $q)) {
                continue;
            }
            $zeilen[] = [
                'nummer' => $a['nummer'], 'name' => $a['name'], 'url' => $a['url'],
                'preis' => ($a['ab'] ? 'ab ' : '').Sammelbestellung::euro($a['preis_cent']),
                'bild' => KatalogBildController::adresse($a['bild']), 'status' => $status, 'waehlbar' => $status === 'neu',
            ];
        }

        return $zeilen;
    }

    public function alleNeuenWaehlen(): void
    {
        $this->auswahl = array_values(array_unique([...$this->auswahl, ...array_column(array_filter($this->zeilen(), fn ($z) => $z['waehlbar']), 'url')]));
    }

    public function keineWaehlen(): void
    {
        $this->auswahl = [];
    }

    /** Ausgewählte Artikel im Hintergrund übernehmen (wie „Mehrere Artikel holen“). */
    public function uebernehmen(): void
    {
        abort_unless(static::canAccess(), 403);
        $gueltig = array_column($this->artikel, null, 'url');
        $urls = array_values(array_filter($this->auswahl, fn ($u) => isset($gueltig[$u]) && ! Sperre::greift($gueltig[$u]['nummer'], $gueltig[$u]['kategorie'])));
        if ($urls === []) {
            Notification::make()->title('Bitte mindestens einen Artikel auswählen.')->warning()->send();

            return;
        }
        if (count($urls) > ArtikelHolen::MAX) {
            Notification::make()->title('Höchstens '.ArtikelHolen::MAX.' auf einmal, ausgewählt sind '.count($urls).'.')->warning()->send();

            return;
        }
        if (ArtikelHolen::stand()['laeuft'] ?? false) {
            Notification::make()->title('Es läuft schon eine Übernahme. Bitte warten, bis sie fertig ist.')->warning()->send();

            return;
        }
        ArtikelHolen::starten($urls, (bool) ($this->data['freigeben'] ?? false), $this->data['shop_kategorie'] ?? null);
        $nutzer = Filament::auth()->user();
        AuditLogger::log('sammelbestellung.katalog.uebernommen', $nutzer instanceof User ? $nutzer : null, 'shop_artikel', null, request());
        $this->auswahl = [];
        Notification::make()->success()->title(count($urls).' Artikel werden übernommen')
            ->body('Das läuft im Hintergrund, etwa eine Sekunde je Größe. Das Ergebnis steht unter „Artikel“ oben.')->send();
    }

    /** @return array{laeuft: bool, gesamt: int, erledigt: int, neu: list<string>, fehler: list<string>, am: string}|null */
    public function stand(): ?array
    {
        return ArtikelHolen::stand();
    }

    private function leeren(): void
    {
        $this->artikel = [];
        $this->auswahl = [];
        $this->fehler = null;
        $this->unvollstaendig = false;
    }

    /**
     * @param  array<string, string>  $kategorien
     * @return array<string, string>
     */
    private static function ohneGesperrte(array $kategorien): array
    {
        return array_filter($kategorien, fn (string $name, string $url) => ! Sperre::greift('', Ruesthaus::kategorie($url)), ARRAY_FILTER_USE_BOTH);
    }
}
