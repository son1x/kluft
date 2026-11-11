<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Services\Zuschuss\ProKopfListe;
use App\Services\Zuschuss\ProKopfNami;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\AuditLogger;
use App\Support\Beitraege;
use App\Support\Features;
use App\Support\NamiAmpel;
use App\Support\Zuschuesse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Pro-Kopf-Zuschuss der Kreisstadt Saarlouis (ZU-19): Kluft erzeugt die
 * namentliche Liste der Jugendlichen als PDF – direkt aus NaMi (Mitglieder
 * des Stamms, Anschriften in Etappen nachgeladen, ProKopfNami) oder aus
 * einem hochgeladenen Mitglieder-Export. Gespeichert wird nichts: Die Datei
 * wird nur gelesen, der Zwischenstand des NaMi-Abrufs liegt verschlüsselt
 * höchstens 30 Minuten im Cache und wird mit dem PDF gelöscht.
 */
class ProKopfZuschussPage extends Page
{
    protected string $view = 'filament.pages.pro-kopf-zuschuss';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Kasse';

    protected static ?string $navigationLabel = 'Pro-Kopf-Zuschuss';

    protected static ?string $title = 'Pro-Kopf-Zuschuss (Kreisstadt Saarlouis)';

    protected static ?string $slug = 'pro-kopf-zuschuss';

    protected static ?int $navigationSort = 60;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Zwischenstand des NaMi-Abrufs für die Anzeige, z. B. „30 von 52 Anschriften“ (keine Personendaten). */
    public ?string $fortschritt = null;

    /** Wie lange der verschlüsselte Zwischenstand höchstens im Cache liegt. */
    private const STAND_MINUTEN = 30;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return Zuschuesse::aktiv() && config('dpsg.site.level') === 'Stamm' && $user && ($user->darf('zuschuesse') || $user->isKasse());
    }

    public function mount(): void
    {
        $this->form->fill(['jahr' => (string) now()->year]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Select::make('jahr')->label('Antragsjahr (Stichtag 01.01.)')->required()->selectablePlaceholder(false)->options(self::jahre()),
            FileUpload::make('datei')->label('Mitglieder-Export aus NaMi (.xlsx oder .csv)')->required()->storeFiles(false)
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                ->maxSize(5120)
                ->helperText('In NaMi die Mitglieder des Stamms suchen und „Export“ wählen. Nötig sind die Spalten Nachname, Vorname, Straße, PLZ, Ort und Geburtsdatum; Status und Mitgliedstyp werden berücksichtigt, wenn vorhanden.'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nami')
                ->label('Aus NaMi abrufen')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => config('mitglieder.driver') === 'nami')
                ->modalHeading('Liste direkt aus NaMi')
                ->modalDescription(fn () => new HtmlString(e('Kluft holt die Mitglieder des Stamms '.(Beitraege::stammNummer() ?? '(DPSG-Nummer fehlt)')
                    .' aus NaMi, nimmt aktive Mitglieder unter 18 am Stichtag auf (ohne Schnuppermitglieder) und lädt deren Anschrift nach – bei vielen Jugendlichen in mehreren Schritten, die Seite fragt selbst weiter. Gespeichert wird nichts.')
                    .(NamiVerzeichnis::persoenlich() ? ' '.e(self::gespeicherterZugang(self::nutzer())
                        ? 'Die Anmeldung bei NaMi läuft über deinen im Profil gespeicherten Zugang.'
                        : 'Du meldest dich mit deinem eigenen NaMi-Konto an; das Passwort wird nicht gespeichert.') : '')
                    .(NamiVerzeichnis::persoenlich() ? '<div class="mt-3 text-start">'.NamiAmpel::html(self::nutzer())->toHtml().'</div>' : '')))
                ->fillForm(fn () => ['jahr' => (string) ($this->data['jahr'] ?? now()->year)])
                ->schema(fn () => [
                    Select::make('jahr')->label('Antragsjahr (Stichtag 01.01.)')->required()->selectablePlaceholder(false)->options(self::jahre()),
                    ...NamiVerzeichnis::persoenlich() && ! self::gespeicherterZugang(self::nutzer()) ? [
                        TextInput::make('nami_user')->label('Deine NaMi-Mitgliedsnummer')->required()->autocomplete('username'),
                        TextInput::make('nami_password')->label('Dein NaMi-Passwort')->password()->required()->autocomplete('current-password'),
                    ] : [],
                ])
                ->modalSubmitActionLabel('Abrufen')
                ->action(fn (array $data, Action $action) => $this->namiBeginnen($data, $action)),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function namiBeginnen(array $data, Action $action): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $nummer = Beitraege::stammNummer();
        if ($nummer === null) {
            Notification::make()->danger()->title('Die DPSG-Nummer des Stamms fehlt.')->body('Sie steht in der Einrichtung bzw. bei der Gruppierung des Stamms.')->send();
            $action->halt();
        }
        $nutzer = self::nutzer();
        $nami = new NamiVerzeichnis;
        $session = null;
        try {
            if (NamiVerzeichnis::persoenlich()) {
                $session = self::gespeicherterZugang($nutzer)
                    ? $nami->anmeldenMitProfil($nutzer)
                    : $nami->anmelden((string) ($data['nami_user'] ?? ''), (string) ($data['nami_password'] ?? ''));
            }
            $stand = app(ProKopfNami::class)->beginnen($nami, (string) $nummer, Carbon::create((int) $data['jahr'], 1, 1)->startOfDay());
        } catch (MitgliederverzeichnisException $e) {
            Notification::make()->danger()->title('NaMi-Abruf fehlgeschlagen')->body($e->getMessage())->send();

            return null;
        }

        $this->data['jahr'] = (string) $data['jahr'];
        $this->standMerken($stand + ['session' => $session]);
        AuditLogger::log('zuschuss.pro_kopf.nami:'.$data['jahr'], $nutzer, request: request());

        return $this->weiter();
    }

    /**
     * Nächste Etappe: Anschriften für höchstens mitglieder.per_run Jugendliche,
     * danach fragt die Seite selbst weiter; am Ende das PDF.
     */
    public function weiter(): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $stand = $this->standLesen();
        if ($stand === null) {
            $this->fortschritt = null;
            Notification::make()->warning()->title('Der Abruf ist abgelaufen. Bitte neu starten.')->send();

            return null;
        }

        $nami = new NamiVerzeichnis;
        if (is_array($stand['session'] ?? null)) {
            $nami->mitSession($stand['session']);
        }
        if (self::gespeicherterZugang(self::nutzer())) {
            $nami->mitZugang(self::nutzer());
        }
        try {
            $stand = app(ProKopfNami::class)->weiter($nami, $stand, max(1, (int) config('mitglieder.per_run', 30)));
        } catch (MitgliederverzeichnisException $e) {
            $this->abbrechen();
            Notification::make()->danger()->title('NaMi-Abruf abgebrochen')->body($e->getMessage())->send();

            return null;
        }

        if (! ProKopfNami::fertig($stand)) {
            $this->standMerken($stand);
            // Ohne Zutun weiter; der Knopf „Weiter“ auf der Seite bleibt als Rückfall.
            $this->js('$wire.weiter()');

            return null;
        }

        $this->abbrechen();
        $stichtag = Carbon::parse($stand['stichtag']);
        $this->melden(count($stand['personen']), $stand['ausgelassen'], (int) $stand['ohne_anschrift']);
        $pdf = app(ZuschussPdf::class)->proKopf(ProKopfNami::personen($stand), $stichtag, new Event);

        return response()->streamDownload(fn () => print ($pdf), 'pro-kopf-zuschuss-'.$stichtag->year.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /** Zwischenstand verwerfen (auch der Knopf „Abbrechen“). */
    public function abbrechen(): void
    {
        Cache::forget($this->standSchluessel());
        $this->fortschritt = null;
    }

    /** @param  array<string, mixed>  $stand */
    private function standMerken(array $stand): void
    {
        Cache::put($this->standSchluessel(), Crypt::encrypt($stand), now()->addMinutes(self::STAND_MINUTEN));
        $fertig = count($stand['personen']);
        $this->fortschritt = "{$fertig} von {$stand['gesamt']} Anschriften aus NaMi geholt …";
    }

    /** @return array<string, mixed>|null */
    private function standLesen(): ?array
    {
        $wert = Cache::get($this->standSchluessel());
        try {
            $stand = is_string($wert) ? Crypt::decrypt($wert) : null;
        } catch (DecryptException) {
            $stand = null;
        }

        return is_array($stand) ? $stand : null;
    }

    private function standSchluessel(): string
    {
        return 'pro-kopf-nami:'.Filament::auth()->id();
    }

    /** @param  array{zu_alt: int, kein_mitglied: int, ohne_datum: int}  $a */
    private function melden(int $n, array $a, int $ohneAnschrift = 0): void
    {
        Notification::make()->{$n >= 5 && $ohneAnschrift === 0 ? 'success' : 'warning'}()
            ->title($n.' Jugendliche in der Liste'.($n < 5 ? ' – die Stadt verlangt mindestens 5' : ''))
            ->body("Nicht aufgenommen: {$a['zu_alt']} ab 18, {$a['kein_mitglied']} keine aktiven Mitglieder (z. B. Schnupper), {$a['ohne_datum']} ohne lesbares Geburtsdatum."
                .($ohneAnschrift > 0 ? " Bei {$ohneAnschrift} hat NaMi keine Anschrift geliefert – bitte im PDF von Hand ergänzen." : ''))
            ->persistent()->send();
    }

    /** @return array<int, string> Jahr => Beschriftung (PHP macht aus „2027“ als Schlüssel eine Zahl) */
    private static function jahre(): array
    {
        return collect([now()->year + 1, now()->year, now()->year - 1])->mapWithKeys(fn (int $j) => [(string) $j => (string) $j])->all();
    }

    private static function nutzer(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function gespeicherterZugang(?User $user): bool
    {
        return $user !== null && $user->hasNamiZugang() && Features::enabled(Features::NAMI_PROFIL);
    }

    public function erzeugen(): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $daten = $this->form->getState();
        $datei = is_array($daten['datei'] ?? null) ? reset($daten['datei']) : ($daten['datei'] ?? null);
        if (! $datei instanceof TemporaryUploadedFile) {
            Notification::make()->danger()->title('Bitte eine Datei hochladen.')->send();

            return null;
        }
        $stichtag = Carbon::create((int) $daten['jahr'], 1, 1)->startOfDay();
        $ergebnis = app(ProKopfListe::class)->lesen($datei->getRealPath(), $datei->getClientOriginalName(), $stichtag);
        $datei->delete();
        $this->form->fill(['jahr' => $daten['jahr']]);

        if ($ergebnis['fehler'] !== []) {
            Notification::make()->danger()->title(implode(' ', $ergebnis['fehler']))->send();

            return null;
        }
        $this->melden(count($ergebnis['personen']), $ergebnis['ausgelassen']);
        AuditLogger::log('zuschuss.pro_kopf:'.$stichtag->year, Filament::auth()->user(), request: request());
        $pdf = app(ZuschussPdf::class)->proKopf($ergebnis['personen'], $stichtag, new Event);

        return response()->streamDownload(fn () => print ($pdf), 'pro-kopf-zuschuss-'.$stichtag->year.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
