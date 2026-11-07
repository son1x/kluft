<?php

namespace App\Filament\Resources\GeorgsboteIssues\Pages;

use App\Filament\Resources\GeorgsboteIssues\GeorgsboteIssueResource;
use App\Filament\Resources\GeorgsboteIssues\Pages\Concerns\MeldetTitelbilder;
use App\Filament\Resources\GeorgsboteIssues\Pages\Concerns\TracksPrivacyCheck;
use App\Models\GeorgsboteIssue;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditGeorgsboteIssue extends EditRecord
{
    use MeldetTitelbilder;
    use TracksPrivacyCheck;

    protected static string $resource = GeorgsboteIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('titelbild')
                ->label('Titelbild aus PDF erzeugen')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->visible(fn (GeorgsboteIssue $record) => ! $record->istBrief() && filled($record->pdf_path) && GeorgsboteIssueResource::canEdit($record))
                ->modalHeading('Titelbild aus Seite 1 des PDFs')
                // Vorhandenes Titelbild nur nach Bestätigung ersetzen.
                ->modalDescription(fn (GeorgsboteIssue $record) => $record->cover_path
                    ? 'Es gibt schon ein Titelbild. Es wird durch Seite 1 des gespeicherten PDFs ersetzt.'
                    : 'Verwendet das gespeicherte PDF. Ein gerade neu gewähltes PDF erst speichern.')
                ->modalContent(fn (GeorgsboteIssue $record) => view('filament.georgsbote.titelbilder', [
                    'ausgaben' => [$record->titelbildAuftrag()],
                    'ueberschreiben' => filled($record->cover_path),
                    // Ohne vorhandenes Titelbild gleich loslegen, sonst erst nach Klick.
                    'automatisch' => blank($record->cover_path),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Schließen'),
            DeleteAction::make()
                ->after(function ($record) {
                    Storage::disk('local')->delete($record->pdf_path);
                    if ($record->cover_path) {
                        Storage::disk('public')->delete($record->cover_path);
                    }
                }),
        ];
    }

    /** Neues Titelbild ins Formular übernehmen, sonst würde „Speichern“ es wieder leeren. */
    protected function nachTitelbildern(): void
    {
        $this->record->refresh();
        $this->refreshFormData(['cover_path']);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->withPrivacyCheck($data, $this->record->privacy_checked);
    }
}
