<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\User;
use App\Support\AnmeldeInfos;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Sammeladresse für Info-Mails zu Anmeldungen (App\Support\AnmeldeInfos), nur Verwaltung.
            Action::make('sammeladresse')
                ->label('Sammeladresse')
                ->icon(Heroicon::OutlinedInboxStack)
                ->color('gray')
                ->visible(fn () => (bool) Filament::auth()->user()?->isVerwaltung())
                ->modalHeading('Sammeladresse für Anmeldungen')
                ->modalDescription('An diese Adresse geht bei jeder Veranstaltung mit „Sammeladresse informieren“ eine kurze Mail, wenn jemand die Anmeldung bestätigt, nachrückt oder storniert. Leer: die Kontaktadresse der Website.')
                ->fillForm(fn () => ['adresse' => AnmeldeInfos::sammeladresse()])
                ->schema([
                    TextInput::make('adresse')->label('E-Mail-Adresse')->email()->maxLength(255)
                        ->placeholder((string) config('dpsg.contact.email')),
                ])
                ->modalSubmitActionLabel('Speichern')
                ->action(function (array $data) {
                    $user = Filament::auth()->user();
                    AnmeldeInfos::sammeladresseSetzen($data['adresse'] ?? null, $user?->getKey());
                    AuditLogger::log('anmeldungen.sammeladresse', $user instanceof User ? $user : null, request: request());
                    Notification::make()->success()->title('Gespeichert')->body('Sammeladresse: '.(AnmeldeInfos::sammeladresse() ?? '–'))->send();
                }),
            CreateAction::make(),
        ];
    }
}
