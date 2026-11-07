<?php

namespace App\Filament\Pages;

use App\Models\Seitenaufruf;
use App\Support\Aufrufe as AufrufeZaehler;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Seitenaufrufe ohne Personenbezug (docs/aufrufe.md): Summe und Verlauf im
 * Zeitraum, meistaufgerufene Seiten. Für Verwaltung, Vorstand und Admins.
 */
class Aufrufe extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.aufrufe';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Aufrufe';

    protected static ?string $title = 'Aufrufe';

    protected static ?string $slug = 'aufrufe';

    protected static ?int $navigationSort = 15;

    #[Url]
    public string $zeitraum = '30';

    /** @var array<string, string|null> Pfad => Titel (je Anfrage) */
    private array $titel = [];

    public static function canAccess(): bool
    {
        return AufrufeZaehler::zugriff(Filament::auth()->user());
    }

    public function updatedZeitraum(): void
    {
        if (! array_key_exists($this->zeitraum, AufrufeZaehler::ZEITRAEUME)) {
            $this->zeitraum = '30';
        }
        $this->resetPage();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                [$von, $bis] = AufrufeZaehler::zeitraum($this->zeitraum);

                return AufrufeZaehler::imZeitraum($von, $bis)
                    ->select('pfad')->selectRaw('sum(aufrufe) as summe')->groupBy('pfad');
            })
            ->columns([
                TextColumn::make('pfad')
                    ->label('Seite')
                    ->formatStateUsing(fn (Seitenaufruf $record) => $this->titelFuer($record->pfad) ?? $record->pfad)
                    ->description(fn (Seitenaufruf $record) => $this->titelFuer($record->pfad) ? $record->pfad : null)
                    ->url(fn (Seitenaufruf $record) => url($record->pfad))
                    ->openUrlInNewTab()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $pfade = AufrufeZaehler::pfadeMitTitel($search);
                        $like = '%'.mb_strtolower(str_replace(['%', '_', '\\'], '', $search)).'%';

                        return $query->where(fn (Builder $query) => $query->where('pfad', 'like', $like)->orWhereIn('pfad', $pfade));
                    }),
                TextColumn::make('summe')
                    ->label('Aufrufe')
                    ->numeric(thousandsSeparator: '.')
                    ->alignEnd()
                    // Bei Gleichstand nach Pfad, damit die Reihenfolge über Seiten stabil bleibt.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('summe', $direction)->orderBy('pfad')),
            ])
            ->defaultSort('summe', 'desc')
            // Die Tabelle hat bewusst keine eigene Kennung (nur Tag, Pfad, Anzahl).
            ->defaultKeySort(false)
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Im Zeitraum keine Aufrufe gezählt');
    }

    public function getTableRecordKey(Model|array $record): string
    {
        return is_array($record) ? (string) ($record['pfad'] ?? '') : (string) $record->getAttribute('pfad');
    }

    private function titelFuer(string $pfad): ?string
    {
        if (! array_key_exists($pfad, $this->titel)) {
            $this->titel[$pfad] = AufrufeZaehler::titel([$pfad])[$pfad] ?? null;
        }

        return $this->titel[$pfad];
    }
}
