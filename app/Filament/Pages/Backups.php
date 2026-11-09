<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Backup\BackupEinspielen;
use App\Services\Backup\BackupService;
use App\Support\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use RuntimeException;
use UnitEnum;

/**
 * Verwaltung → Backups (nur Admins): Stand, Backup jetzt erstellen und ein
 * Backup einspielen – auch eines von Test auf Live (docs/livegang.md).
 * Ausgeführt wird im Cronjob (App\Services\Backup\BackupEinspielen); den
 * Fortschritt zeigt /einspielen/stand/{token}.
 */
class Backups extends Page
{
    protected string $view = 'filament.pages.backups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Backups';

    protected static ?string $title = 'Backups';

    protected static ?string $slug = 'backups';

    protected static ?int $navigationSort = 48;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isAdmin();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('jetzt')
                ->label('Backup jetzt erstellen')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->disabled(fn () => BackupEinspielen::laeuft())
                ->requiresConfirmation()
                ->modalDescription('Sichert Datenbank, geschützte Dateien und Uploads verschlüsselt. Startet innerhalb einer Minute im Hintergrund; danach steht es unten in der Liste, auch zum Einspielen auf einer anderen Website im selben Webspace (z. B. Test → Live).')
                ->action(fn () => $this->beauftragen('backup')),
            Action::make('einspielen')
                ->label('Backup einspielen')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('danger')
                ->disabled(fn () => BackupEinspielen::laeuft())
                ->modalHeading('Backup einspielen')
                ->modalDescription('Ersetzt ALLE Inhalte dieser Website durch den Stand im Backup: Datenbank (auch Benutzer und Einstellungen), geschützte Dateien samt Schlüssel und alle Uploads. Danach bitte neu anmelden. Läuft im Hintergrund, die Website ist währenddessen im Wartungsmodus.')
                ->schema([
                    Select::make('datei')->label('Backup')->required()->searchable()
                        ->options(fn () => app(BackupEinspielen::class)->quellen())
                        ->helperText('Eigene Backups und die anderer Kluft-Installationen im selben Webspace (z. B. test.dpsg-trier.de). Ein Backup von woanders vorher per Dateimanager nach storage/app/private/backups legen. Das Backup-Passwort muss dasselbe sein.'),
                    Text::make(fn () => 'Zur Bestätigung den Namen dieser Website eintippen: '.request()->getHost()),
                    TextInput::make('bestaetigung')->label('Website')->required()
                        ->in(fn () => [request()->getHost()])
                        ->validationMessages(['in' => 'Bitte genau „'.request()->getHost().'“ eintippen.']),
                ])
                ->modalSubmitActionLabel('Einspielen')
                ->action(fn (array $data) => $this->beauftragen('einspielen', (string) $data['datei'])),
            Action::make('uebertragen')
                ->label('Auf andere Website übertragen')
                ->icon('heroicon-o-arrow-up-on-square-stack')
                ->color('warning')
                ->visible(fn () => app(BackupEinspielen::class)->ziele() !== [])
                ->disabled(fn () => BackupEinspielen::laeuft())
                ->modalHeading('Auf andere Website übertragen')
                ->modalDescription('Erstellt ein frisches Backup dieser Website und lässt es auf der gewählten Website einspielen, z. B. beim Livegang von Test auf die neue Live-Website (dort muss sich dafür niemand anmelden können). ALLE Inhalte der Ziel-Website werden ersetzt. Voraussetzungen: Cronjob dort eingerichtet, dasselbe BACKUP_PASSWORD.')
                ->schema([
                    Select::make('ziel')->label('Ziel-Website (Ordner im Webspace)')->required()
                        ->options(fn () => app(BackupEinspielen::class)->ziele()),
                    TextInput::make('bestaetigung')->label('Zur Bestätigung den Ordnernamen des Ziels eintippen')->required()
                        ->rule(fn (Get $get) => fn (string $attribute, mixed $value, \Closure $fail) => basename((string) $get('ziel')) === trim((string) $value) ? null : $fail('Bitte genau den Ordnernamen des Ziels eintippen.')),
                ])
                ->modalSubmitActionLabel('Übertragen')
                ->action(fn (array $data) => $this->beauftragen('uebertragen', (string) $data['ziel'])),
        ];
    }

    private function beauftragen(string $art, ?string $datei = null): void
    {
        abort_unless(static::canAccess(), 403);
        $erlaubt = $art === 'uebertragen' ? app(BackupEinspielen::class)->ziele() : app(BackupEinspielen::class)->quellen();
        if ($datei !== null && ! array_key_exists($datei, $erlaubt)) {
            Notification::make()->danger()->title('Dieses Backup bzw. Ziel gibt es nicht (mehr).')->send();

            return;
        }
        $user = Filament::auth()->user();
        try {
            $token = BackupEinspielen::beauftragen($art, $datei, $user?->email);
        } catch (RuntimeException $e) {
            Notification::make()->warning()->title($e->getMessage())->send();

            return;
        }
        AuditLogger::log(match ($art) {
            'backup' => 'backup.angefordert', 'uebertragen' => 'backup.uebertragen', default => 'backup.einspielen'
        }, $user instanceof User ? $user : null, request: request());
        $this->redirect(route('backup.stand', $token));
    }

    /**
     * @return array{letztes: ?array<string, mixed>, pruefung: ?array<string, mixed>, liste: array<string, string>, stand: ?array<string, mixed>, passwort: bool}
     */
    public function daten(): array
    {
        return [
            'letztes' => BackupService::lastRun(),
            'pruefung' => BackupService::lastVerify(),
            'liste' => app(BackupEinspielen::class)->quellen(),
            'stand' => BackupEinspielen::stand(),
            'passwort' => mb_strlen((string) config('dpsg.backup.password')) >= 16,
        ];
    }
}
