<?php

namespace App\Filament\Resources\GeorgsboteIssues\Pages;

use App\Filament\Resources\GeorgsboteIssues\GeorgsboteIssueResource;
use App\Filament\Resources\GeorgsboteIssues\Pages\Concerns\MeldetTitelbilder;
use App\Models\GeorgsboteIssue;
use App\Support\AuditLogger;
use App\Support\GeorgsboteArchiv;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;

class ListGeorgsboteIssues extends ListRecords
{
    use MeldetTitelbilder;

    protected static string $resource = GeorgsboteIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('oeffentlich')
                ->label('Öffentliches Archiv')
                ->url(route('georgsbote.index'))
                ->openUrlInNewTab()
                ->color('gray'),
            Action::make('mehrere')
                ->label('Mehrere PDFs hochladen')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn () => GeorgsboteIssueResource::canCreate())
                ->modalHeading('Alte Jahrgänge auf einmal hochladen')
                ->modalDescription('Jahrgang und Ausgabe werden aus dem Dateinamen gelesen, z. B. „Georgsbote_2019_2.pdf“ (2. Ausgabe im Jahrgang 2019) oder „Georgsbrief_2019_02.pdf“ (Georgsbrief Februar 2019). Titel und Inhalt kommen aus der Archivliste, soweit vorhanden. Alle Ausgaben kommen offline ins Archiv – Personenbezug und Veröffentlichung danach je Ausgabe prüfen. Größe je Datei: bis '.self::grenzeMb().' MB.')
                ->schema([
                    FileUpload::make('files')
                        ->label('PDF-Dateien')
                        ->multiple()
                        ->maxFiles(50)
                        ->disk('local')
                        ->directory('georgsbote')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(51200)
                        ->storeFileNamesIn('names')
                        ->required(),
                ])
                ->modalSubmitActionLabel('Hochladen')
                ->action(fn (array $data) => $this->importFiles($data['files'] ?? [], $data['names'] ?? [])),
            Action::make('archivliste')
                ->label('Infos aus der Archivliste übernehmen')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->visible(fn () => GeorgsboteArchiv::vorhanden() && GeorgsboteIssueResource::canCreate())
                ->requiresConfirmation()
                ->modalDescription('Setzt Titel (nur wo noch der Vorgabe-Titel steht) und Kurzinhalt (nur wo er leer ist) aus der Archivliste. Im Backend Geändertes bleibt.')
                ->action(function () {
                    $n = GeorgsboteIssue::query()->get()->filter(fn (GeorgsboteIssue $issue) => GeorgsboteArchiv::anwenden($issue))->count();
                    Notification::make()->title("{$n} Ausgabe(n) ergänzt")->success()->send();
                }),
            // Titelbilder aus Seite 1 der PDFs, für Georgsboten und Georgsbriefe auf einmal (resources/js/titelbild.js).
            Action::make('titelbilder')
                ->label('Fehlende Titelbilder erzeugen')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->visible(fn () => GeorgsboteIssueResource::canCreate() && GeorgsboteIssue::query()->ohneTitelbild()->exists())
                ->modalHeading('Fehlende Titelbilder aus Seite 1 der PDFs erzeugen')
                ->modalContent(fn () => view('filament.georgsbote.titelbilder', [
                    'ausgaben' => self::auftraege(GeorgsboteIssue::query()->ohneTitelbild()),
                    'ueberschreiben' => false,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Schließen'),
            Action::make('titelbilderNeu')
                ->label('Alle Titelbilder neu erzeugen')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => GeorgsboteIssueResource::canCreate() && GeorgsboteIssue::query()->mitPdf()->exists())
                ->modalHeading('Alle Titelbilder neu aus Seite 1 der PDFs erzeugen')
                ->modalDescription('Vorhandene Titelbilder, auch von Hand hochgeladene, werden ersetzt.')
                ->modalContent(fn () => view('filament.georgsbote.titelbilder', [
                    'ausgaben' => self::auftraege(GeorgsboteIssue::query()->mitPdf()),
                    'ueberschreiben' => true,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Schließen'),
            CreateAction::make(),
        ];
    }

    /**
     * Aufträge für den Renderer: Georgsboten zuerst, je Art die neuesten zuerst.
     *
     * @param  Builder<GeorgsboteIssue>  $query
     * @return list<array{id: int, label: string, pdfUrl: string, uploadUrl: string}>
     */
    private static function auftraege(Builder $query): array
    {
        return $query->orderByRaw('case when art = ? then 0 else 1 end', [GeorgsboteIssue::BOTE])
            ->orderByDesc('year')->orderByDesc('issue_no')->get()
            ->map(fn (GeorgsboteIssue $issue) => $issue->titelbildAuftrag())->values()->all();
    }

    /** Knopf „Titelbilder jetzt erzeugen“ in der Meldung nach dem Hochladen. */
    #[On('titelbilder-oeffnen')]
    public function titelbilderOeffnen(): void
    {
        $this->mountAction('titelbilder');
    }

    /** Kleinste der Grenzen: 50 MB im Formular, PHP upload_max_filesize und post_max_size. */
    public static function grenzeMb(): int
    {
        $mb = function (string $wert): int {
            $wert = trim($wert);
            $zahl = (float) $wert;

            return (int) match (strtolower(substr($wert, -1))) {
                'g' => $zahl * 1024,
                'k' => $zahl / 1024,
                'm' => $zahl,
                default => $zahl / 1024 / 1024,
            };
        };

        $grenzen = array_filter([50, $mb((string) ini_get('upload_max_filesize')), $mb((string) ini_get('post_max_size'))], fn (int $g) => $g > 0);

        return min($grenzen);
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'bote' => Tab::make('Georgsbote')
                ->badge(fn () => GeorgsboteIssue::query()->where('art', GeorgsboteIssue::BOTE)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('art', GeorgsboteIssue::BOTE)),
            'brief' => Tab::make('Georgsbrief')
                ->badge(fn () => GeorgsboteIssue::query()->where('art', GeorgsboteIssue::BRIEF)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('art', GeorgsboteIssue::BRIEF)),
        ];
    }

    /**
     * @param  list<string>  $paths
     * @param  array<string, string>  $names  Pfad => ursprünglicher Dateiname
     */
    private function importFiles(array $paths, array $names): void
    {
        abort_unless(GeorgsboteIssueResource::canCreate(), 403);

        $created = [];
        $skipped = [];
        $neue = 0;
        foreach ($paths as $path) {
            $name = $names[$path] ?? basename($path);
            $guess = GeorgsboteIssue::erkennen($name);

            if (! $guess) {
                $skipped[] = "{$name} (Jahrgang/Ausgabe bzw. Monat nicht erkannt)";
            } elseif ($vorhanden = GeorgsboteIssue::query()->where($guess)->first()) {
                $skipped[] = "{$name} ({$vorhanden->label()} gibt es schon)";
            } else {
                $archiv = GeorgsboteArchiv::eintrag($guess['art'], $guess['year'], $guess['issue_no']);
                $issue = GeorgsboteIssue::query()->create([
                    ...$guess,
                    'title' => $archiv['title'] ?? GeorgsboteIssue::standardTitel($guess['art'], $guess['year'], $guess['issue_no']),
                    'inhalt' => $archiv['inhalt'] ?? null,
                    'pdf_path' => $path,
                    'published' => false,
                    'privacy_checked' => false,
                ]);
                $created[] = $issue->label();
                $neue++;

                continue;
            }

            Storage::disk('local')->delete($path);
        }

        AuditLogger::log('georgsbote.bulk_upload:'.count($created), Filament::auth()->user());

        $ohneTitelbild = $neue > 0 && GeorgsboteIssue::query()->ohneTitelbild()->exists();

        Notification::make()
            ->title(count($created).' Ausgabe(n) angelegt (offline)')
            ->body(collect([
                $created ? 'Angelegt: '.implode(', ', $created).'. Bitte Titel, Inhalt und Personenbezug prüfen, dann veröffentlichen.' : null,
                $skipped ? 'Übersprungen: '.implode('; ', $skipped).'.' : null,
            ])->filter()->implode("\n\n"))
            ->color($skipped ? 'warning' : 'success')
            ->actions($ohneTitelbild ? [
                Action::make('titelbilder')
                    ->label('Titelbilder jetzt erzeugen')
                    ->button()
                    ->dispatch('titelbilder-oeffnen')
                    ->close(),
            ] : [])
            ->persistent()
            ->send();
    }
}
