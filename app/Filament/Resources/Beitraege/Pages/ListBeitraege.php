<?php

namespace App\Filament\Resources\Beitraege\Pages;

use App\Filament\Resources\Beitraege\BeitragResource;
use App\Jobs\BeitraegeAbrufen;
use App\Models\Beitrag;
use App\Models\BeitragsMitglied;
use App\Models\User;
use App\Services\Beitraege\BeitragsAbruf;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\AuditLogger;
use App\Support\Beitraege;
use App\Support\Features;
use App\Support\NamiAmpel;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListBeitraege extends ListRecords
{
    protected static string $resource = BeitragResource::class;

    public function getSubheading(): ?string
    {
        $abruf = Beitraege::letzterAbruf();
        $stand = match ($abruf['status'] ?? null) {
            'laeuft' => 'NaMi-Abruf läuft: '.$abruf['text'],
            'fehler' => 'Letzter NaMi-Abruf fehlgeschlagen ('.$abruf['am']?->format('d.m.Y H:i').'): '.$abruf['text'],
            'fertig' => 'Letzter NaMi-Abruf '.$abruf['am']?->format('d.m.Y H:i').': '.$abruf['text'],
            default => 'Noch kein NaMi-Abruf.',
        };

        return $stand.(Beitraege::saetze() === [] ? ' Bitte zuerst die Beitragssätze eintragen.' : '');
    }

    /** Im Filter gewähltes Halbjahr (sonst das laufende). */
    private function halbjahr(): string
    {
        $wert = (string) ($this->tableFilters['halbjahr']['value'] ?? '');

        return Beitraege::gueltig($wert) ? $wert : Beitraege::aktuell();
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
            Action::make('abrufen')
                ->label('Aus NaMi abrufen')
                ->icon('heroicon-o-arrow-path')
                ->visible(config('mitglieder.driver') === 'nami')
                ->modalHeading('Mitglieder aus NaMi abrufen')
                ->modalDescription(fn () => new HtmlString(e('Kluft holt die Mitglieder des Stamms '.(Beitraege::stammNummer() ?? '(DPSG-Nummer fehlt)')
                    .' mit Beitragsart, Ein- und Austritt und legt die Beiträge des Halbjahres an – im Hintergrund, bei vielen Mitgliedern einige Minuten. '
                    .'Bezahlte Beiträge bleiben, wie sie sind. Schnuppermitglieder und Nicht-Mitglieder zahlen keinen Beitrag und werden nicht übernommen.')
                    .(NamiVerzeichnis::persoenlich() ? ' '.e(self::gespeicherterZugang(self::nutzer())
                        ? 'Die Anmeldung bei NaMi läuft über deinen im Profil gespeicherten Zugang.'
                        : 'Du meldest dich mit deinem eigenen NaMi-Konto an; das Passwort wird nur gespeichert, wenn du es unten ankreuzst.') : '')
                    .'<div class="mt-3 text-start">'.NamiAmpel::html(self::nutzer())->toHtml().'</div>'))
                ->fillForm(fn () => ['halbjahr' => $this->halbjahr()])
                ->schema(fn () => [
                    Select::make('halbjahr')->label('Beiträge anlegen für')->required()
                        ->options(Beitraege::auswahl(Beitrag::query()->distinct()->pluck('halbjahr')->all())),
                    ...NamiVerzeichnis::persoenlich() && ! self::gespeicherterZugang(self::nutzer()) ? [
                        TextInput::make('nami_user')->label('Deine NaMi-Mitgliedsnummer')->required()->autocomplete('username'),
                        TextInput::make('nami_password')->label('Dein NaMi-Passwort')->password()->required()->autocomplete('current-password'),
                        Toggle::make('nami_speichern')->label('Zugang verschlüsselt in meinem Profil speichern (dann keine Eingabe mehr nötig)')
                            ->visible(Features::enabled(Features::NAMI_PROFIL)),
                    ] : [],
                ])
                ->action(function (array $data, Action $action) {
                    abort_unless(BeitragResource::canViewAny(), 403);
                    $nummer = Beitraege::stammNummer();
                    $halbjahr = (string) $data['halbjahr'];
                    if ($nummer === null || ! Beitraege::gueltig($halbjahr)) {
                        Notification::make()->title('Die DPSG-Nummer des Stamms fehlt.')->body('Sie steht in der Einrichtung bzw. bei der Gruppierung des Stamms.')->danger()->send();
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
                            Notification::make()->title('NaMi-Anmeldung fehlgeschlagen')->body($e->getMessage())->danger()->send();
                            $action->halt();
                        }
                        if ($nutzer && ! self::gespeicherterZugang($nutzer) && Features::enabled(Features::NAMI_PROFIL) && ($data['nami_speichern'] ?? false)) {
                            $nutzer->forceFill(['nami_user' => trim((string) $data['nami_user']), 'nami_password' => (string) $data['nami_password'], 'nami_checked_at' => now(), 'nami_error' => null])->save();
                            AuditLogger::log('nami.zugang.gespeichert', $nutzer, request: request());
                        }
                    }

                    Beitraege::abrufStand('laeuft', 'gestartet …');
                    BeitraegeAbrufen::dispatch((string) $nummer, $halbjahr, $session, self::gespeicherterZugang($nutzer) ? $nutzer?->getKey() : null);
                    AuditLogger::log('beitraege.abruf:'.$halbjahr, $nutzer, request: request());
                    Notification::make()->title('Abruf gestartet – die Liste füllt sich in den nächsten Minuten.')->success()->send();
                }),
            Action::make('saetze')
                ->label('Beitragssätze')
                ->icon('heroicon-o-currency-euro')
                ->color('gray')
                ->modalHeading('Beitrag je Halbjahr nach Beitragsart')
                ->modalDescription('Der Beitrag ist Bundesbeitrag plus Stammesanteil, jeweils für ein Halbjahr. Den Bundesbeitrag stellt der Bundesverband in Rechnung (Betrag laut Rechnung bzw. Beitragsordnung), den Stammesanteil beschließt der Stamm. Offene Beiträge ab dem laufenden Halbjahr werden angepasst, bezahlte und ältere nicht.')
                ->fillForm(fn () => collect(self::arten())->flatMap(fn (string $art, string $feld) => [
                    $feld.'_bund' => BeitragResource::alsEuro(Beitraege::saetze()[$art]['bund'] ?? null),
                    $feld.'_stamm' => BeitragResource::alsEuro(Beitraege::saetze()[$art]['stamm'] ?? null),
                ])->all())
                ->schema(fn () => collect(self::arten())->map(fn (string $art, string $feld) => Fieldset::make($art)->columns(2)->schema([
                    TextInput::make($feld.'_bund')->label('Bundesbeitrag')->suffix('€')->rules(BeitragResource::EURO)->inputMode('decimal'),
                    TextInput::make($feld.'_stamm')->label('Stammesanteil')->suffix('€')->rules(BeitragResource::EURO)->inputMode('decimal'),
                ]))->values()->all())
                ->action(function (array $data) {
                    abort_unless(BeitragResource::canViewAny(), 403);
                    $saetze = [];
                    foreach (self::arten() as $feld => $art) {
                        $saetze[$art] = ['bund' => (int) BeitragResource::alsCent($data[$feld.'_bund'] ?? null), 'stamm' => (int) BeitragResource::alsCent($data[$feld.'_stamm'] ?? null)];
                    }
                    Beitraege::saetzeSetzen($saetze, Filament::auth()->id());
                    $angepasst = 0;
                    foreach (Beitrag::query()->offen()->where('halbjahr', '>=', Beitraege::aktuell())->distinct()->pluck('halbjahr') as $halbjahr) {
                        $angepasst += app(BeitragsAbruf::class)->halbjahrAnlegen((string) $halbjahr)['angepasst'];
                    }
                    AuditLogger::log('beitraege.saetze', self::nutzer(), request: request());
                    Notification::make()->title('Beitragssätze gespeichert'.($angepasst ? " – {$angepasst} offene Beiträge angepasst." : '.'))->success()->send();
                }),
            Action::make('export')
                ->label('Offene als CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    abort_unless(BeitragResource::canViewAny(), 403);
                    $halbjahr = $this->halbjahr();
                    AuditLogger::log('beitraege.export:'.$halbjahr, self::nutzer(), request: request());

                    return response()->streamDownload(function () use ($halbjahr) {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, ['Nachname', 'Vorname', 'Mitgliedsnummer', 'Beitragsart', 'Soll', 'davon Bundesbeitrag', 'Bezahlt', 'Offen', 'Notiz'], ';');
                        Beitrag::query()->offen()->where('halbjahr', $halbjahr)->with('mitglied')->get()
                            ->sortBy(fn (Beitrag $b) => $b->mitglied?->name())
                            ->each(fn (Beitrag $b) => fputcsv($out, [
                                $b->mitglied?->nachname, $b->mitglied?->vorname, $b->mitglied?->mitgliedsnummer, $b->beitragsart,
                                BeitragResource::alsEuro($b->soll_cent), BeitragResource::alsEuro($b->bundes_cent), BeitragResource::alsEuro($b->bezahlt_cent),
                                BeitragResource::alsEuro(max(0, $b->soll_cent - (int) $b->bezahlt_cent)), $b->notiz,
                            ], ';'));
                        fclose($out);
                    }, 'beitraege-offen-'.$halbjahr.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }

    /**
     * Beitragsarten für die Sätze: die bekannten und alle bei Mitgliedern
     * vorkommenden. Schlüssel sind Feldnamen (Bezeichnungen enthalten Sonderzeichen).
     *
     * @return array<string, string>
     */
    private static function arten(): array
    {
        $arten = ['Voller Beitrag', 'Voller Beitrag – Stiftungseuro', 'Familienermäßigt', 'Familienermäßigt – Stiftungseuro', 'Ermäßigt', 'Ermäßigt – Stiftungseuro', 'Übernahme'];
        $arten = array_values(array_unique([...$arten, ...array_keys(Beitraege::saetze()),
            ...BeitragsMitglied::query()->whereNotNull('beitragsart')->distinct()->pluck('beitragsart')->all()]));

        return array_combine(array_map(fn (int $i) => 'art_'.$i, array_keys($arten)), $arten);
    }
}
