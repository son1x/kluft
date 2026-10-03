<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\People\PersonResource;
use App\Filament\Resources\Users\UserResource;
use App\Services\PrivacyRequests;
use App\Support\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Datenschutz-Anfragen (Pflichtenheft 9.2 „Betroffenenrechte“, Rollenmatrix
 * „Datenschutzfunktionen“): Auskunft, Berichtigung und Löschung pro Person
 * über die E-Mail-Adresse. Im Audit-Log steht nur, dass gesucht, exportiert
 * oder gelöscht wurde – nicht die Adresse.
 */
class PrivacyRequestsPage extends Page
{
    protected string $view = 'filament.pages.privacy-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Datenschutz-Anfragen';

    protected static ?string $title = 'Datenschutz-Anfragen';

    protected static ?string $slug = 'datenschutz-anfragen';

    protected static ?int $navigationSort = 93;

    public string $email = '';

    public ?string $searched = null;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isVerwaltung();
    }

    public function search(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['email' => ['required', 'email', 'max:255']], [], ['email' => 'E-Mail-Adresse']);
        $this->searched = mb_strtolower(trim($this->email));
        AuditLogger::log('privacy.search', Filament::auth()->user(), request: request());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'found' => $this->searched ? app(PrivacyRequests::class)->find($this->searched) : null,
            'eventUrl' => fn ($event) => EventResource::getUrl('edit', ['record' => $event]),
            'personUrl' => fn ($person) => PersonResource::getUrl('edit', ['record' => $person]),
            'userUrl' => fn ($user) => UserResource::getUrl('edit', ['record' => $user]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Auskunft herunterladen (ZIP)')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => $this->searched !== null)
                ->action(function (PrivacyRequests $requests): BinaryFileResponse {
                    abort_unless(static::canAccess() && $this->searched, 403);
                    AuditLogger::log('privacy.export', Filament::auth()->user(), request: request());

                    return response()->download($requests->export($this->searched), 'auskunft-'.now()->format('Y-m-d').'.zip')->deleteFileAfterSend();
                }),
            Action::make('delete')
                ->label('Daten löschen')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => $this->searched !== null)
                ->requiresConfirmation()
                ->modalHeading('Daten zu dieser Adresse löschen?')
                ->modalDescription('Gelöscht werden alle Veranstaltungsanmeldungen samt hochgeladener Rechnungen und Bescheinigungen, Personenvorstellungen und wartende Mails zu dieser Adresse. Frei werdende Plätze gehen an die Warteliste. Redaktionskonten bitte unter „Benutzer“ deaktivieren. Das lässt sich nicht rückgängig machen – bei Bedarf vorher die Auskunft herunterladen.')
                ->modalSubmitActionLabel('Endgültig löschen')
                ->action(function (PrivacyRequests $requests): void {
                    abort_unless(static::canAccess() && $this->searched, 403);
                    $result = $requests->delete($this->searched);
                    AuditLogger::log("privacy.delete:{$result['registrations']}+{$result['people']}+{$result['queued']}", Filament::auth()->user(), request: request());

                    Notification::make()
                        ->title('Gelöscht')
                        ->body("{$result['registrations']} Anmeldung(en), {$result['people']} Personenvorstellung(en), {$result['queued']} Mail(s) aus der Warteschlange.")
                        ->success()->send();
                }),
        ];
    }
}
