<?php

namespace App\Filament\Resources\Datenabgleich\Pages;

use App\Filament\Resources\Datenabgleich\DatenabgleichResource;
use App\Jobs\DatenabgleichAusfuehren;
use App\Models\Datenmeldung;
use App\Models\User;
use App\Services\DatenabgleichExport;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\AuditLogger;
use App\Support\Beitraege;
use App\Support\Datenabgleich;
use App\Support\Features;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListDatenabgleich extends ListRecords
{
    protected static string $resource = DatenabgleichResource::class;

    public function getSubheading(): ?string
    {
        $stand = Datenabgleich::stand();
        $meldungen = Datenmeldung::query()->whereNotNull('bestaetigt_am')->count();

        return $meldungen.' bestätigte Meldung(en) über „Meine Daten aktualisieren“'
            .($stand['am'] ? ' · Letzter Abgleich: '.Carbon::parse($stand['am'])->format('d.m.Y H:i').' – '.$stand['text'] : '');
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('link')->label('Link zum Formular')->icon('heroicon-o-link')->color('gray')
                ->modalSubmitAction(false)->modalCancelActionLabel('Schließen')
                ->modalContent(fn () => new HtmlString('<p class="text-sm">Diesen Link an Mitglieder bzw. Eltern schicken (Mail, WhatsApp, Elternbrief):</p><p class="mt-2 select-all rounded bg-gray-100 p-2 font-mono text-sm dark:bg-gray-800">'.e(route('datenmeldung.create')).'</p>')),
            Action::make('abgleichen')->label('Abgleich starten')->icon('heroicon-o-arrow-path')
                ->modalDescription('Kluft holt die Mitgliederliste des Stamms aus NaMi, sucht jede gemeldete Person und vergleicht die Angaben. Die bisherige Liste wird ersetzt. Kluft schreibt nichts in NaMi.')
                ->schema(fn () => NamiVerzeichnis::persoenlich() && ! self::gespeicherterZugang(self::nutzer()) ? [
                    TextInput::make('nami_user')->label('Deine NaMi-Mitgliedsnummer')->required(),
                    TextInput::make('nami_password')->label('Dein NaMi-Passwort')->password()->required(),
                ] : [])
                ->action(function (array $data, Action $action) {
                    abort_unless(DatenabgleichResource::canAccess(), 403);
                    $nummer = Beitraege::stammNummer();
                    if ($nummer === null) {
                        Notification::make()->danger()->title('Die DPSG-Nummer des Stamms fehlt.')->send();
                        $action->halt();
                    }
                    $session = null;
                    $nutzer = self::nutzer();
                    if (NamiVerzeichnis::persoenlich()) {
                        try {
                            $session = self::gespeicherterZugang($nutzer)
                                ? (new NamiVerzeichnis)->anmeldenMitProfil($nutzer)
                                : (new NamiVerzeichnis)->anmelden((string) ($data['nami_user'] ?? ''), (string) ($data['nami_password'] ?? ''));
                        } catch (MitgliederverzeichnisException $e) {
                            Notification::make()->danger()->title('NaMi-Anmeldung fehlgeschlagen')->body($e->getMessage())->send();
                            $action->halt();
                        }
                    }
                    Datenabgleich::standSetzen('laeuft', 'gestartet …');
                    DatenabgleichAusfuehren::dispatch((string) $nummer, $session, self::gespeicherterZugang($nutzer) ? $nutzer?->getKey() : null);
                    AuditLogger::log('datenabgleich.start', $nutzer, request: request());
                    Notification::make()->success()->title('Abgleich gestartet – die Liste füllt sich in den nächsten Minuten.')->send();
                }),
            Action::make('pdf')->label('Liste (PDF)')->icon('heroicon-o-document-text')->color('gray')
                ->action(fn (): StreamedResponse => $this->export('pdf')),
            Action::make('excel')->label('Liste (.xlsx)')->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn (): StreamedResponse => $this->export('xlsx')),
        ];
    }

    private function export(string $art): StreamedResponse
    {
        abort_unless(DatenabgleichResource::canAccess(), 403);
        AuditLogger::log('datenabgleich.export.'.$art, Filament::auth()->user(), request: request());
        $export = app(DatenabgleichExport::class);

        return $art === 'pdf'
            ? response()->streamDownload(fn () => print ($export->pdf()), 'nami-datenabgleich-'.now()->format('Y-m-d').'.pdf', ['Content-Type' => 'application/pdf'])
            : response()->streamDownload(fn () => $export->xlsx(), 'nami-datenabgleich-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
