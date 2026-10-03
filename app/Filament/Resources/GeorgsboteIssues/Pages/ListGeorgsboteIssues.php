<?php

namespace App\Filament\Resources\GeorgsboteIssues\Pages;

use App\Filament\Resources\GeorgsboteIssues\GeorgsboteIssueResource;
use App\Models\GeorgsboteIssue;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
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
                ->modalDescription('Jahrgang und Ausgabe werden aus dem Dateinamen gelesen, z. B. „Georgsbote_2019_2.pdf“, „GB 3-2018.pdf“ oder „2017-04 Georgsbote.pdf“. Alle Ausgaben kommen offline ins Archiv – Titel, Personenbezug und Veröffentlichung danach je Ausgabe prüfen.')
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
            CreateAction::make(),
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
            $guess = GeorgsboteIssue::guessFromFilename($name);

            if (! $guess) {
                $skipped[] = "{$name} (Jahrgang/Ausgabe nicht erkannt)";
            } elseif (GeorgsboteIssue::query()->where($guess)->exists()) {
                $skipped[] = "{$name} (Ausgabe {$guess['issue_no']}/{$guess['year']} gibt es schon)";
            } else {
                $issue = GeorgsboteIssue::query()->create([
                    ...$guess,
                    'title' => "Georgsbote {$guess['issue_no']}/{$guess['year']}",
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
                $created ? 'Angelegt: '.implode(', ', $created).'. Bitte Titel und Personenbezug prüfen, dann veröffentlichen.' : null,
                $skipped ? 'Übersprungen: '.implode('; ', $skipped).'.' : null,
            ])->filter()->implode("\n\n"))
            ->color($skipped ? 'warning' : 'success')
            ->persistent()
            ->send();
    }
}
