<?php

namespace App\Filament\Resources\Videokonferenzen\Pages;

use App\Filament\Resources\Videokonferenzen\VideokonferenzResource;
use App\Models\Videokonferenz;
use App\Services\Jitsi\Einladungen;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVideokonferenz extends ViewRecord
{
    protected static string $resource = VideokonferenzResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('einladen')
                ->label('Einladungen senden')
                ->icon('heroicon-o-envelope')
                ->modalDescription('An jede Adresse geht eine eigene Mail. Bereits Eingeladene bekommen die Mail noch einmal, wenn sie in der Liste stehen; zum Nachladen nur die neuen Adressen eintragen.')
                ->fillForm(fn (Videokonferenz $record) => ['moderation_emails' => [], 'teilnehmende_emails' => []])
                ->schema(VideokonferenzResource::adressFelder())
                ->visible(fn (Videokonferenz $record) => $record->moderatorAblauf()->isFuture())
                ->action(function (Videokonferenz $record, array $data) {
                    abort_unless(VideokonferenzResource::canView($record), 403);
                    $neuM = Einladungen::bereinigen($data['moderation_emails'] ?? []);
                    $neuT = Einladungen::bereinigen($data['teilnehmende_emails'] ?? []);
                    $gesendet = Einladungen::senden($record, $neuM, $neuT);
                    $record->forceFill([
                        'moderation_emails' => Einladungen::bereinigen([...($record->moderation_emails ?? []), ...$neuM]) ?: null,
                        'teilnehmende_emails' => Einladungen::bereinigen([...($record->teilnehmende_emails ?? []), ...$neuT]) ?: null,
                    ])->save();
                    AuditLogger::log('videokonferenz.eingeladen', Filament::auth()->user(), Videokonferenz::class, $record->id, request());
                    Notification::make()->success()->title('Einladungen werden verschickt')
                        ->body("Moderation: {$gesendet['moderation']}, Teilnehmende: {$gesendet['teilnehmende']}.")->send();
                }),
            DeleteAction::make()
                ->modalDescription('Der Raum verschwindet aus der Liste. Bereits verschickte Links funktionieren weiter: der Moderator-Link bis zu seinem Ablauf, der Teilnahme-Link ohnehin.'),
        ];
    }
}
