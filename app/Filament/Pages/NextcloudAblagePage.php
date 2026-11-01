<?php

namespace App\Filament\Pages;

use App\Support\AuditLogger;
use App\Support\NextcloudAblage;
use App\Support\Zuschuesse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * Nextcloud-Ablage für Zuschussunterlagen (ZU-40), nur Admins. Das
 * App-Passwort wird verschlüsselt gespeichert und nie wieder angezeigt.
 */
class NextcloudAblagePage extends Page
{
    protected string $view = 'filament.pages.nextcloud-ablage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Nextcloud-Ablage';

    protected static ?string $title = 'Nextcloud-Ablage';

    protected static ?string $slug = 'nextcloud-ablage';

    protected static ?int $navigationSort = 55;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Zuschuesse::aktiv() && (bool) Filament::auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $zugang = NextcloudAblage::zugang();
        $this->form->fill(['url' => $zugang['url'] ?? null, 'benutzer' => $zugang['benutzer'] ?? null]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('url')->label('WebDAV-Adresse des Ordners')->required()->url()->maxLength(500)
                ->placeholder('https://cloud.example.org/remote.php/dav/files/BENUTZER/Veranstaltungen')
                ->helperText('In Nextcloud: Dateien → Einstellungen (unten links) → WebDAV-Adresse, dahinter den Ordner anhängen.'),
            TextInput::make('benutzer')->label('Benutzer')->required()->maxLength(150),
            TextInput::make('passwort')->label('App-Passwort')->password()->maxLength(200)
                ->required(fn () => NextcloudAblage::zugang() === null)
                ->helperText('In Nextcloud: Persönliche Einstellungen → Sicherheit → „Neues App-Passwort erstellen“. Leer lassen, um das gespeicherte zu behalten.'),
        ]);
    }

    public function speichern(): void
    {
        abort_unless(static::canAccess(), 403);
        $daten = $this->form->getState();
        NextcloudAblage::speichern($daten['url'], $daten['benutzer'], $daten['passwort'] ?? null, Filament::auth()->id());
        AuditLogger::log('nextcloud_ablage.gespeichert', Filament::auth()->user(), request: request());
        $this->data['passwort'] = null;
        Notification::make()->success()->title('Gespeichert')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testen')->label('Verbindung testen')->icon('heroicon-o-signal')
                ->visible(fn () => NextcloudAblage::zugang() !== null)
                ->action(function () {
                    try {
                        $meldung = NextcloudAblage::testen();
                    } catch (Throwable $e) {
                        $meldung = 'Keine Verbindung: '.$e->getMessage();
                    }
                    Notification::make()->title($meldung)->{str_starts_with($meldung, 'Verbindung in Ordnung') ? 'success' : 'warning'}()->send();
                }),
            Action::make('entfernen')->label('Entfernen')->icon('heroicon-o-trash')->color('danger')
                ->visible(fn () => NextcloudAblage::zugang() !== null)
                ->requiresConfirmation()->modalDescription('Kluft legt danach nichts mehr in Nextcloud ab. Dort Abgelegtes bleibt.')
                ->action(function () {
                    NextcloudAblage::entfernen();
                    AuditLogger::log('nextcloud_ablage.entfernt', Filament::auth()->user(), request: request());
                    $this->form->fill();
                }),
        ];
    }
}
