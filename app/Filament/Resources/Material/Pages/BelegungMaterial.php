<?php

namespace App\Filament\Resources\Material\Pages;

use App\Filament\Resources\Material\MaterialResource;
use App\Filament\Resources\Material\ReservierungResource;
use App\Models\Material;
use App\Models\MaterialReservierung;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Belegungskalender je Material: Monat mit Ampel je Tag (frei, teilweise,
 * voll) und die Reservierungen des Monats.
 */
class BelegungMaterial extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MaterialResource::class;

    protected string $view = 'filament.pages.material-belegung';

    #[Url]
    public string $monat = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(MaterialResource::canViewAny(), 403);
        if (! preg_match('/^\d{4}-\d{2}$/', $this->monat)) {
            $this->monat = today()->format('Y-m');
        }
    }

    public function getTitle(): string
    {
        return 'Belegung: '.$this->material()->name;
    }

    public function material(): Material
    {
        /** @var Material $m */
        $m = $this->record;

        return $m;
    }

    public function blaettern(int $richtung): void
    {
        $this->monat = $this->start()->addMonths($richtung)->format('Y-m');
    }

    public function heute(): void
    {
        $this->monat = today()->format('Y-m');
    }

    public function start(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->monat.'-01')->startOfDay();
    }

    /**
     * @return Collection<int, MaterialReservierung>
     */
    public function reservierungen(): Collection
    {
        return $this->material()->reservierungen()
            ->whereIn('status', ['angefragt', 'zugesagt', 'ausgegeben'])
            ->ueberschneidet($this->start(), $this->start()->endOfMonth())
            ->orderBy('von')->get();
    }

    /**
     * Wochen mit Tagen: [datum, imMonat, fest, angefragt, stufe].
     *
     * @return list<list<array{tag: Carbon, imMonat: bool, fest: int, angefragt: int, stufe: string}>>
     */
    public function wochen(): array
    {
        $reservierungen = $this->reservierungen();
        $menge = max(1, $this->material()->menge);
        $tag = $this->start()->startOfWeek(Carbon::MONDAY);
        $ende = $this->start()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $wochen = [];
        while ($tag->lte($ende)) {
            $woche = [];
            for ($i = 0; $i < 7; $i++) {
                $amTag = $reservierungen->filter(fn (MaterialReservierung $r) => $r->von->lte($tag) && $r->bis->gte($tag));
                $fest = (int) $amTag->whereIn('status', MaterialReservierung::FEST)->sum('menge');
                $woche[] = [
                    'tag' => $tag->copy(),
                    'imMonat' => $tag->month === $this->start()->month,
                    'fest' => $fest,
                    'angefragt' => (int) $amTag->where('status', 'angefragt')->sum('menge'),
                    'stufe' => $fest === 0 ? 'frei' : ($fest >= $menge ? 'voll' : 'teilweise'),
                ];
                $tag->addDay();
            }
            $wochen[] = $woche;
        }

        return $wochen;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reservieren')->label('Reservieren')->icon('heroicon-o-plus')
                ->url(fn () => ReservierungResource::getUrl('create', ['material' => $this->material()->id])),
            Action::make('bearbeiten')->label('Material bearbeiten')->color('gray')
                ->url(fn () => MaterialResource::getUrl('edit', ['record' => $this->material()])),
        ];
    }
}
