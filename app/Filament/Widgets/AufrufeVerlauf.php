<?php

namespace App\Filament\Widgets;

use App\Support\Aufrufe;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

/**
 * Verlauf der Seitenaufrufe auf der Seite „Aufrufe“ (nicht im Dashboard).
 * Chart.js bringt Filament mit, es kommt vom eigenen Server.
 */
class AufrufeVerlauf extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    #[Reactive]
    public string $zeitraum = '30';

    public static function canView(): bool
    {
        return Aufrufe::zugriff(Filament::auth()->user());
    }

    public function getHeading(): string
    {
        return 'Verlauf – '.(Aufrufe::ZEITRAEUME[$this->zeitraum] ?? Aufrufe::ZEITRAEUME['30']);
    }

    public function getDescription(): string
    {
        [$von, $bis] = Aufrufe::zeitraum($this->zeitraum);

        return 'Summe im Zeitraum: '.number_format(Aufrufe::summe($von, $bis), 0, ',', '.').' Aufrufe ('.$von->format('d.m.Y').' bis '.$bis->format('d.m.Y').')';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        [$von, $bis] = Aufrufe::zeitraum($this->zeitraum);
        $verlauf = Aufrufe::verlauf($von, $bis);

        return [
            'datasets' => [['label' => 'Aufrufe', 'data' => array_values($verlauf)]],
            'labels' => array_keys($verlauf),
        ];
    }

    protected function getOptions(): array
    {
        return [
            // Deutsche Zahlen auf der Achse (1.600 statt 1,600).
            'locale' => 'de-DE',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
