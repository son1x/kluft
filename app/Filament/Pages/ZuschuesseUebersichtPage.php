<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Antrag;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Alle Zuschussanträge auf einen Blick (Phase 4): je Veranstaltung und
 * Zuschussgeber Frist, Stand, geschätzt, beantragt, bewilligt.
 */
class ZuschuesseUebersichtPage extends Page
{
    protected string $view = 'filament.pages.zuschuesse-uebersicht';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Termine & Veranstaltungen';

    protected static ?string $navigationLabel = 'Zuschussanträge';

    protected static ?string $title = 'Zuschussanträge';

    protected static ?string $slug = 'zuschussantraege';

    protected static ?int $navigationSort = 15;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return Zuschuesse::aktiv() && $user && ($user->darf('zuschuesse') || $user->isKasse());
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }
        $n = collect(self::zeilen())->sum(fn (array $e) => collect($e['geber'])->filter(fn (array $z) => $z['status'] === 'offen' && $z['frist']->lte(now()->addDays(14)))->count());

        return $n > 0 ? (string) $n : null;
    }

    /** @return list<array{event: Event, geber: list<array<string, mixed>>}> */
    public static function zeilen(): array
    {
        return Event::query()->where('zuschuss', true)->where('starts_at', '>=', now()->subMonths(18))->orderByDesc('starts_at')->get()
            ->map(fn (Event $e) => ['event' => $e, 'geber' => Antrag::uebersicht($e)])->all();
    }

    protected function getViewData(): array
    {
        return ['zeilen' => self::zeilen()];
    }
}
