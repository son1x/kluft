<?php

namespace App\Filament\Resources\Shop;

use App\Support\Sammelbestellung;
use Filament\Facades\Filament;

/** Bereich „Sammelbestellung“: Funktion an und Rolle „Sammelbestellung“ (oder Admin). */
trait Zugriff
{
    public static function canAccess(): bool
    {
        return Sammelbestellung::zugriff(Filament::auth()->user());
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }
}
