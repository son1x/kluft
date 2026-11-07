<?php

namespace App\Filament\Resources\GeorgsboteIssues\Pages;

use App\Filament\Resources\GeorgsboteIssues\GeorgsboteIssueResource;
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

class ListGeorgsboteIssues extends ListRecords
{
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
                ->modalDescription('Jahrgang und Ausgabe werden aus dem Dateinamen gelesen, z. B. „Georgsbote_2019_2.pdf“ (2. Ausgabe im Jahrgang 2019) oder „Georgsbrief_2019_02.pdf“ (Georgsbrief Februar 2019). Titel und Inhalt kommen aus der Archivliste, soweit vorhanden. Alle Ausgaben kommen offline ins Archiv – Personenbezug und Veröffentlichung danach je Ausgabe prüfen.')
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
            CreateAction::make(),
        ];
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

                continue;
            }

            Storage::disk('local')->delete($path);
        }

        AuditLogger::log('georgsbote.bulk_upload:'.count($created), Filament::auth()->user());

        Notification::make()
            ->title(count($created).' Ausgabe(n) angelegt (offline)')
            ->body(collect([
                $created ? 'Angelegt: '.implode(', ', $created).'. Bitte Titel, Inhalt und Personenbezug prüfen, dann veröffentlichen.' : null,
                $skipped ? 'Übersprungen: '.implode('; ', $skipped).'.' : null,
            ])->filter()->implode("\n\n"))
            ->color($skipped ? 'warning' : 'success')
            ->persistent()
            ->send();
    }
}
