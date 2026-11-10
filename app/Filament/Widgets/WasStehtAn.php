<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\WasStehtAn as Quellen;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Dashboard: „Was steht an“, je nach Rollen (App\Support\WasStehtAn).
 */
class WasStehtAn extends Widget
{
    protected string $view = 'filament.widgets.was-steht-an';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->roleAssignments->isNotEmpty();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return ['eintraege' => Quellen::fuer($user)];
    }
}
