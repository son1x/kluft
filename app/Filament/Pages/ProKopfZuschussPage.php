<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Services\Zuschuss\ProKopfListe;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\AuditLogger;
use App\Support\Zuschuesse;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Pro-Kopf-Zuschuss der Kreisstadt Saarlouis (ZU-19): Mitglieder-Export aus
 * NaMi hochladen, Kluft erzeugt die namentliche Liste der Jugendlichen als
 * PDF. Die Datei wird nur gelesen, nichts davon gespeichert.
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

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return Zuschuesse::aktiv() && config('dpsg.site.level') === 'Stamm' && $user && ($user->isVerwaltung() || $user->isKasse());
    }

    public function mount(): void
    {
        $this->form->fill(['jahr' => (string) now()->year]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Select::make('jahr')->label('Antragsjahr (Stichtag 01.01.)')->required()->selectablePlaceholder(false)
                ->options(collect([now()->year + 1, now()->year, now()->year - 1])->mapWithKeys(fn (int $j) => [(string) $j => (string) $j])->all()),
            FileUpload::make('datei')->label('Mitglieder-Export aus NaMi (.xlsx oder .csv)')->required()->storeFiles(false)
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                ->maxSize(5120)
                ->helperText('In NaMi die Mitglieder des Stamms suchen und „Export“ wählen. Nötig sind die Spalten Nachname, Vorname, Straße, PLZ, Ort und Geburtsdatum; Status und Mitgliedstyp werden berücksichtigt, wenn vorhanden.'),
        ]);
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
        $a = $ergebnis['ausgelassen'];
        $n = count($ergebnis['personen']);
        Notification::make()->{$n >= 5 ? 'success' : 'warning'}()
            ->title($n.' Jugendliche in der Liste'.($n < 5 ? ' – die Stadt verlangt mindestens 5' : ''))
            ->body("Nicht aufgenommen: {$a['zu_alt']} ab 18, {$a['kein_mitglied']} keine aktiven Mitglieder (z. B. Schnupper), {$a['ohne_datum']} ohne lesbares Geburtsdatum.")
            ->persistent()->send();
        AuditLogger::log('zuschuss.pro_kopf:'.$stichtag->year, Filament::auth()->user(), request: request());
        $pdf = app(ZuschussPdf::class)->proKopf($ergebnis['personen'], $stichtag, new Event);

        return response()->streamDownload(fn () => print ($pdf), 'pro-kopf-zuschuss-'.$stichtag->year.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
