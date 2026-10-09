<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Update\Release;
use App\Services\Update\UpdateFehler;
use App\Services\Update\Updater;
use App\Support\AuditLogger;
use App\Support\KluftVersion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Verwaltung → Updates (Admins): installierte Version, verfügbare Version
 * mit Änderungen, Update anstoßen und Protokoll des letzten Laufs. Das
 * Update selbst läuft im Cronjob (kluft:update --angefordert), nie im
 * Web-Request.
 */
class Updates extends Page
{
    protected string $view = 'filament.pages.updates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Updates';

    protected static ?string $title = 'Kluft-Updates';

    protected static ?string $slug = 'updates';

    protected static ?int $navigationSort = 46;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isAdmin();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $anfrage = Cache::get(Updater::CACHE_ANGEFORDERT);

        return [
            'modus' => Updater::modus(),
            'version' => KluftVersion::version(),
            'revision' => KluftVersion::kurz(),
            'release' => Updater::bekannt(),
            'geprueft' => Updater::zuletztGeprueft(),
            'anfrage' => is_array($anfrage) ? $anfrage : null,
            'lauf' => Updater::letzterLauf(),
            'adresse' => config('kluft.updates.manifest_url'),
            'schluessel' => filled(config('kluft.updates.public_key')),
        ];
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('pruefen')
                ->label('Nach Updates suchen')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->visible(fn () => Updater::aktiv())
                ->action(function (Updater $updater) {
                    try {
                        $release = $updater->pruefen();
                    } catch (UpdateFehler $e) {
                        Notification::make()->danger()->title('Prüfung nicht möglich')->body($e->getMessage())->send();

                        return;
                    }
                    Notification::make()->success()
                        ->title($release ? 'Kluft '.$release->version.' ist verfügbar.' : 'Kluft ist aktuell.')
                        ->send();
                }),
            Action::make('installieren')
                ->label(fn () => 'Auf '.(Updater::bekannt()->version ?? '…').' aktualisieren')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => Updater::aktiv() && Updater::bekannt() instanceof Release && ! Cache::has(Updater::CACHE_ANGEFORDERT))
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Auf Kluft '.Updater::bekannt()?->version.' aktualisieren?')
                ->modalDescription('Das Update startet innerhalb einer Minute im Hintergrund. Vorher wird ein Backup erstellt; die Website ist dann etwa ein bis zwei Minuten im Wartungsmodus. Klappt etwas nicht, läuft die bisherige Version weiter. Ergebnis per Mail und hier im Protokoll.')
                ->modalSubmitActionLabel('Update starten')
                ->action(function () {
                    $release = Updater::bekannt();
                    $user = Filament::auth()->user();
                    if (! $release) {
                        return;
                    }
                    Cache::forever(Updater::CACHE_ANGEFORDERT, ['version' => $release->version, 'von' => $user?->getKey(), 'zeit' => now()->timestamp]);
                    AuditLogger::log('update.angefordert', $user instanceof User ? $user : null, request: request());
                    Notification::make()->success()->title('Update angefordert')->body('Es startet mit dem nächsten Lauf des Cronjobs, meist innerhalb einer Minute.')->send();
                }),
        ];
    }
}
