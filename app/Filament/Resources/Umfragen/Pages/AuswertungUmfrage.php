<?php

namespace App\Filament\Resources\Umfragen\Pages;

use App\Filament\Resources\Umfragen\UmfrageResource;
use App\Models\Umfrage;
use App\Models\UmfrageAntwort;
use App\Services\Umfragen\Auswertung;
use App\Services\Umfragen\Export;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Auswertung einer Umfrage: Zusammenfassung mit Balken, alle Antworten
 * einer Person nacheinander, Gewinnspiel; Export als PDF (A4) und Excel.
 */
class AuswertungUmfrage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = UmfrageResource::class;

    protected string $view = 'filament.umfragen.auswertung';

    /** zusammenfassung | einzeln | gewinnspiel */
    public string $ansicht = 'zusammenfassung';

    /** Laufende Nummer der Teilnahme in der Einzelansicht (ab 1). */
    public int $nr = 1;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(UmfrageResource::canViewAny() && ! $this->umfrage()->ist_vorlage, 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Auswertung: '.$this->umfrage()->titel;
    }

    public function umfrage(): Umfrage
    {
        /** @var Umfrage */
        return $this->getRecord();
    }

    public function zeige(string $ansicht): void
    {
        $this->ansicht = in_array($ansicht, ['zusammenfassung', 'einzeln', 'gewinnspiel'], true) ? $ansicht : 'zusammenfassung';
    }

    public function blaettern(int $schritt): void
    {
        $this->nr = max(1, min($this->umfrage()->antworten()->count(), $this->nr + $schritt));
    }

    public function auslosen(): void
    {
        abort_unless(UmfrageResource::canViewAny(), 403);
        $gewinn = $this->umfrage()->gewinnspielEintraege()->where('gewonnen', false)->inRandomOrder()->first();
        if (! $gewinn) {
            Notification::make()->title('Keine (weiteren) Einträge zum Auslosen.')->warning()->send();

            return;
        }
        $gewinn->update(['gewonnen' => true]);
        AuditLogger::log('umfrage.gewinnspiel.ausgelost', Filament::auth()->user(), Umfrage::class, $this->umfrage()->id, request());
        Notification::make()->title('Ausgelost: '.$gewinn->name)->success()->send();
    }

    public function gewinnspielLoeschen(): void
    {
        abort_unless(UmfrageResource::canViewAny(), 403);
        $n = $this->umfrage()->gewinnspielEintraege()->delete();
        AuditLogger::log('umfrage.gewinnspiel.geloescht:'.$n, Filament::auth()->user(), Umfrage::class, $this->umfrage()->id, request());
        Notification::make()->title("{$n} Einträge gelöscht.")->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('PDF (A4)')->icon('heroicon-o-document-arrow-down')->color('gray')
                ->action(function (Export $export) {
                    AuditLogger::log('umfrage.export.pdf', Filament::auth()->user(), Umfrage::class, $this->umfrage()->id, request());
                    $pdf = $export->pdf($this->umfrage());

                    return response()->streamDownload(fn () => print ($pdf), Str::slug($this->umfrage()->titel).'-auswertung.pdf', ['Content-Type' => 'application/pdf']);
                }),
            Action::make('excel')->label('Excel')->icon('heroicon-o-table-cells')->color('gray')
                ->action(function (Export $export): StreamedResponse {
                    AuditLogger::log('umfrage.export.excel', Filament::auth()->user(), Umfrage::class, $this->umfrage()->id, request());

                    return $export->excel($this->umfrage());
                }),
            UmfrageResource::einladenAction()->record($this->umfrage()),
            Action::make('bearbeiten')->label('Bearbeiten')->icon('heroicon-o-pencil-square')
                ->url(fn () => UmfrageResource::getUrl('edit', ['record' => $this->umfrage()])),
        ];
    }

    protected function getViewData(): array
    {
        $umfrage = $this->umfrage();
        $anzahl = $umfrage->antworten()->count();
        $this->nr = max(1, min($anzahl, $this->nr));

        return [
            'umfrage' => $umfrage,
            'anzahl' => $anzahl,
            'auswertung' => $this->ansicht === 'zusammenfassung' ? app(Auswertung::class)->zusammenfassung($umfrage) : null,
            'antwort' => $this->ansicht === 'einzeln' && $anzahl > 0
                ? $umfrage->antworten()->with('registration')->orderBy('id')->skip($this->nr - 1)->first() : null,
            'fragen' => $umfrage->fragen()->get(),
            'gewinnspiel' => $this->ansicht === 'gewinnspiel' ? $umfrage->gewinnspielEintraege()->orderByDesc('gewonnen')->orderBy('id')->get() : collect(),
            'einladungen' => $umfrage->nurEingeladene()
                ? ['gesamt' => $umfrage->einladungen()->count(), 'teilgenommen' => $umfrage->einladungen()->whereNotNull('teilgenommen_am')->count()] : null,
        ];
    }

    /** Für die Einzelansicht. */
    public static function wert(?UmfrageAntwort $antwort, string $schluessel): ?array
    {
        return $antwort?->daten[$schluessel] ?? null;
    }
}
