<?php

namespace App\Filament\Pages;

use App\Models\IntegrationToken;
use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramException;
use App\Support\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

/**
 * Integrationen und Tokens – nur Admin/Super-Admin (Pflichtenheft 5.4
 * „Systemeinstellungen, Integrationen, Tokens“). Tokens werden
 * verschlüsselt gespeichert und nie wieder angezeigt.
 */
class Integrations extends Page
{
    protected string $view = 'filament.pages.integrations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $navigationLabel = 'Integrationen';

    protected static ?string $title = 'Integrationen';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('access_token')
                    ->label('Instagram-Zugangstoken (Long-Lived)')
                    ->password()
                    ->required()
                    ->maxLength(1000)
                    ->helperText('Aus der Meta-App (Instagram API mit Instagram-Login). Das Token wird verschlüsselt gespeichert und hier nicht wieder angezeigt.'),
            ]);
    }

    public function saveInstagram(InstagramClient $instagram): void
    {
        abort_unless(static::canAccess(), 403);
        $token = trim((string) $this->form->getState()['access_token']);

        try {
            $account = $instagram->me($token);
        } catch (InstagramException $e) {
            Notification::make()->title('Token wurde nicht gespeichert')->body($e->getMessage())->danger()->send();

            return;
        }

        IntegrationToken::query()->updateOrCreate(['provider' => InstagramClient::PROVIDER], [
            'account_id' => $account['user_id'],
            'account_name' => $account['username'],
            'access_token' => $token,
            // Long-Lived-Tokens gelten ca. 60 Tage; der genaue Wert kommt mit
            // der ersten Erneuerung.
            'expires_at' => now()->addDays(60),
            'refreshed_at' => now(),
            'last_error' => null,
        ]);
        AuditLogger::log('integration.instagram.connect', Filament::auth()->user(), request: request());

        $this->form->fill();
        Notification::make()->title('Instagram verbunden: @'.$account['username'])->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Verbindung testen')
                ->icon('heroicon-o-signal')
                ->visible(fn () => IntegrationToken::for(InstagramClient::PROVIDER) !== null)
                ->action(function (InstagramClient $instagram) {
                    try {
                        $account = $instagram->me();
                        Notification::make()->title('Verbindung ok: @'.$account['username'])->success()->send();
                    } catch (InstagramException $e) {
                        Notification::make()->title('Verbindung fehlgeschlagen')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('refresh')
                ->label('Token jetzt erneuern')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => IntegrationToken::for(InstagramClient::PROVIDER) !== null)
                ->action(function () {
                    $this->callRefreshCommand();
                }),
            Action::make('disconnect')
                ->label('Trennen')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Löscht das gespeicherte Instagram-Token. Bereits veröffentlichte Beiträge bleiben auf Instagram.')
                ->visible(fn () => IntegrationToken::for(InstagramClient::PROVIDER) !== null)
                ->action(function () {
                    IntegrationToken::for(InstagramClient::PROVIDER)?->delete();
                    AuditLogger::log('integration.instagram.disconnect', Filament::auth()->user(), request: request());
                    Notification::make()->title('Instagram getrennt')->success()->send();
                }),
        ];
    }

    private function callRefreshCommand(): void
    {
        Artisan::call('instagram:refresh-token', ['--force' => true]);
        $token = IntegrationToken::for(InstagramClient::PROVIDER);

        $token?->last_error
            ? Notification::make()->title('Erneuerung fehlgeschlagen')->body($token->last_error)->danger()->send()
            : Notification::make()->title('Token erneuert, gültig bis '.$token?->expires_at?->format('d.m.Y'))->success()->send();
    }

    protected function getViewData(): array
    {
        return ['instagram' => IntegrationToken::for(InstagramClient::PROVIDER)];
    }
}
