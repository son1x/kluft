<?php

namespace App\Filament\Pages;

use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Support\AuditLogger;
use App\Support\WhatsAppAccess;
use App\Support\WhatsAppMode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Verbindung zur WhatsApp Cloud API (Setup-Anleitung Schritte 8 und 9 in
 * docs/whatsapp_newsletter.md). Nur WhatsApp-Admins und Admins.
 * Token und App-Secret werden verschlüsselt gespeichert und nie angezeigt.
 *
 * @property Schema $form
 */
class WhatsAppConnection extends Page
{
    protected string $view = 'filament.pages.whatsapp-connection';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'WhatsApp-Newsletter';

    protected static ?string $navigationLabel = 'Verbindung';

    protected static ?string $title = 'WhatsApp-Verbindung';

    protected static ?string $slug = 'whatsapp/verbindung';

    protected static ?int $navigationSort = 4;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return WhatsAppMode::api() && WhatsAppAccess::connect(Filament::auth()->user());
    }

    public function mount(): void
    {
        $settings = app(WhatsAppSettings::class);
        $this->form->fill([
            'phone_number_id' => $settings->phoneNumberId(),
            'waba_id' => $settings->get('waba_id'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(WhatsAppSettings::class);

        return $schema->statePath('data')->columns(2)->components([
            TextInput::make('token')->label('Zugangstoken (Systembenutzer)')->password()->maxLength(2000)
                ->required(fn () => ! $settings->configured())
                ->helperText($settings->configured() ? 'Gespeichert. Nur ausfüllen, um es zu ersetzen.' : 'Business-Einstellungen → Systembenutzer → Token generieren (Ablauf „Nie“).'),
            TextInput::make('app_secret')->label('App-Secret')->password()->maxLength(200)
                ->required(fn () => $settings->get('app_secret') === null)
                ->helperText($settings->get('app_secret') ? 'Gespeichert. Nur ausfüllen, um es zu ersetzen.' : 'App-Dashboard → Einstellungen → Allgemein.'),
            TextInput::make('phone_number_id')->label('Phone-Number-ID')->required()->regex('/^\d+$/')
                ->helperText('WhatsApp Manager → Telefonnummern → Nummer anklicken.'),
            TextInput::make('waba_id')->label('WhatsApp-Business-Konto-ID (WABA)')->required()->regex('/^\d+$/'),
        ]);
    }

    public function save(WhatsAppSettings $settings): void
    {
        abort_unless(static::canAccess(), 403);
        $settings->save($this->form->getState());
        AuditLogger::log('integration.whatsapp.gespeichert', Filament::auth()->user(), request: request());

        $this->form->fill(['phone_number_id' => $settings->phoneNumberId(), 'waba_id' => $settings->get('waba_id')]);
        Notification::make()->success()->title('Gespeichert')->body('Jetzt „Verbindung prüfen“ und den Webhook bei Meta eintragen.')->send();
    }

    protected function getHeaderActions(): array
    {
        $configured = fn () => app(WhatsAppSettings::class)->configured();
        $call = function (callable $step, string $done) {
            try {
                $step();
                Notification::make()->success()->title($done)->send();
            } catch (WhatsAppException $e) {
                Notification::make()->danger()->title('Meta meldet einen Fehler')->body($e->getMessage().' (Code '.$e->metaCode.')')->send();
            }
        };

        return [
            Action::make('check')
                ->label('Verbindung prüfen')
                ->icon('heroicon-o-signal')
                ->visible($configured)
                ->action(fn (WhatsAppClient $client) => $call(function () use ($client) {
                    Cache::forever('whatsapp:health', ['ok' => true, 'status' => $client->phoneStatus(), 'at' => now()->toIso8601String()]);
                }, 'Verbindung ok')),
            Action::make('subscribe')
                ->label('Webhook abonnieren')
                ->icon('heroicon-o-bell')
                ->visible($configured)
                ->action(fn (WhatsAppClient $client) => $call(fn () => $client->subscribeApp(), 'App ist am WhatsApp-Konto abonniert')),
            Action::make('register')
                ->label('Nummer registrieren')
                ->icon('heroicon-o-phone')
                ->visible($configured)
                ->schema([
                    TextInput::make('pin')->label('PIN der Zwei-Schritt-Verifizierung')->password()->required()->regex('/^\d{6}$/')
                        ->helperText('6 Ziffern aus Schritt 5 der Anleitung. Wird nicht gespeichert.'),
                ])
                ->action(fn (array $data, WhatsAppClient $client) => $call(fn () => $client->register($data['pin']), 'Nummer für die Cloud API registriert')),
            Action::make('verifyToken')
                ->label('Neues Verify-Token')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->visible($configured)
                ->requiresConfirmation()
                ->modalDescription('Danach muss das neue Token auch bei Meta im Webhook eingetragen werden.')
                ->action(function (WhatsAppSettings $settings) {
                    $settings->regenerateVerifyToken();
                    Notification::make()->success()->title('Neues Verify-Token erzeugt')->send();
                }),
            Action::make('disconnect')
                ->label('Trennen')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible($configured)
                ->requiresConfirmation()
                ->modalDescription('Löscht Token und Zugangsdaten. Abonnent:innen bleiben erhalten, es kann aber nichts mehr versendet oder empfangen werden.')
                ->action(function (WhatsAppSettings $settings) {
                    $settings->forget();
                    Cache::forget('whatsapp:health');
                    AuditLogger::log('integration.whatsapp.getrennt', Filament::auth()->user(), request: request());
                    Notification::make()->success()->title('WhatsApp getrennt')->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        $settings = app(WhatsAppSettings::class);

        return [
            'configured' => $settings->configured(),
            'webhookUrl' => route('webhooks.whatsapp'),
            'verifyToken' => $settings->get('verify_token'),
            'health' => Cache::get('whatsapp:health'),
            'quality' => Cache::get('whatsapp:quality'),
            'testMode' => (bool) config('whatsapp.test_mode'),
        ];
    }
}
