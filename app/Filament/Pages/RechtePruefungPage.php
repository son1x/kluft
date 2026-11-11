<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\RechtePruefung;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Rechte-Prüfung: einmal im Jahr durchsehen, wer sensible Rechte hat, und
 * den Abschluss festhalten (docs/datenschutz-ueberblick.md).
 */
class RechtePruefungPage extends Page
{
    protected string $view = 'filament.pages.rechte-pruefung';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Rechte-Prüfung';

    protected static ?string $title = 'Rechte-Prüfung';

    protected static ?string $slug = 'rechte-pruefung';

    protected static ?int $navigationSort = 42;

    public static function canAccess(): bool
    {
        return RechtePruefung::zugriff(Filament::auth()->user());
    }

    public function getSubheading(): ?string
    {
        return 'Wer kann Teilnehmendendaten, Zahlungen, Präventions-Nachweise oder Konten sehen bzw. verwalten? Einmal im Jahr durchsehen: Braucht jede Person das noch? Rollen änderst du unter Benutzer.';
    }

    /** @return Collection<int, array<string, mixed>> */
    public function personen(): Collection
    {
        return RechtePruefung::personen();
    }

    /** @return array{am: Carbon, von: ?string}|null */
    public function letzte(): ?array
    {
        return RechtePruefung::letzte();
    }

    public function benutzerUrl(int $id): ?string
    {
        return UserResource::canViewAny() ? UserResource::getUrl('edit', ['record' => $id]) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('abschliessen')
                ->label('Prüfung abschließen')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->requiresConfirmation()
                ->modalHeading('Prüfung abschließen?')
                ->modalDescription('Bestätigt, dass ihr alle Personen in der Liste durchgesehen habt und jede ihre Rechte noch braucht. Datum und Person werden gespeichert und protokolliert; in 12 Monaten erinnert „Was steht an“ an die nächste Prüfung.')
                ->modalSubmitActionLabel('Abschließen')
                ->action(function () {
                    abort_unless(static::canAccess(), 403);
                    RechtePruefung::abschliessen(Filament::auth()->user());
                    Notification::make()->title('Prüfung abgeschlossen')->success()->send();
                }),
        ];
    }
}
