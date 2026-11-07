<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Aufrufe as AufrufeSeite;
use App\Support\Aufrufe;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/** Dashboard: die fünf meistaufgerufenen Seiten der letzten 30 Tage. */
class Meistgelesen extends Widget
{
    protected string $view = 'filament.widgets.meistgelesen';

    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return Aufrufe::zugriff(Filament::auth()->user());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        [$von, $bis] = Aufrufe::zeitraum('30');
        $top = Aufrufe::top($von, $bis, 5);
        $titel = Aufrufe::titel($top->pluck('pfad')->all());

        return [
            'eintraege' => $top->map(fn (object $zeile) => (object) [
                'pfad' => $zeile->pfad,
                'titel' => $titel[$zeile->pfad] ?? $zeile->pfad,
                'aufrufe' => $zeile->aufrufe,
            ]),
            'seite' => AufrufeSeite::getUrl(),
        ];
    }
}
