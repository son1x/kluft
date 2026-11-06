<?php

namespace App\Filament\Resources\Videokonferenzen\Pages;

use App\Filament\Resources\Videokonferenzen\VideokonferenzResource;
use App\Services\Jitsi\Einladungen;
use App\Support\AuditLogger;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateVideokonferenz extends CreateRecord
{
    protected static string $resource = VideokonferenzResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Filament::auth()->id();
        $data['moderation_emails'] = Einladungen::bereinigen($data['moderation_emails'] ?? []) ?: null;
        $data['teilnehmende_emails'] = Einladungen::bereinigen($data['teilnehmende_emails'] ?? []) ?: null;

        return $data;
    }

    protected function afterCreate(): void
    {
        AuditLogger::log('videokonferenz.angelegt', Filament::auth()->user(), $this->record::class, $this->record->getKey(), request());

        $gesendet = Einladungen::senden($this->record);
        if ($gesendet['moderation'] + $gesendet['teilnehmende'] > 0) {
            Notification::make()->success()->title('Einladungen werden verschickt')
                ->body("Moderation: {$gesendet['moderation']}, Teilnehmende: {$gesendet['teilnehmende']}.")->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return VideokonferenzResource::getUrl('view', ['record' => $this->record]);
    }
}
