<?php

namespace App\Filament\Resources\Datenabgleich;

use App\Filament\Resources\Datenabgleich\Pages\ListDatenabgleich;
use App\Models\DatenabgleichZeile;
use App\Support\AuditLogger;
use App\Support\Datenabgleich;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * NaMi-Datenabgleich (docs/pflichtenheft-nami-datenabgleich.md): Unterschiede
 * zwischen den Meldungen der Mitglieder und NaMi; in NaMi korrigieren und
 * hier abhaken. Kluft schreibt nichts in NaMi.
 */
class DatenabgleichResource extends Resource
{
    protected static ?string $model = DatenabgleichZeile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Kasse';

    protected static ?string $navigationLabel = 'NaMi-Datenabgleich';

    protected static ?string $modelLabel = 'Abgleich-Zeile';

    protected static ?string $pluralModelLabel = 'NaMi-Datenabgleich';

    protected static ?string $slug = 'nami-datenabgleich';

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return Datenabgleich::aktiv() && $user && ($user->isVerwaltung() || $user->isKasse());
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }
        $n = DatenabgleichZeile::query()->where('status', 'abweichung')->whereNull('erledigt_am')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('status')
            ->emptyStateHeading('Noch kein Abgleich')
            ->emptyStateDescription('Oben „Abgleich starten“. Vorher sollten Meldungen über „Meine Daten aktualisieren“ eingegangen sein (Link oben).')
            ->columns([
                TextColumn::make('name')->label('Person')->weight('bold')
                    ->state(fn (DatenabgleichZeile $r) => $r->ergebnis['name'] ?? '')
                    ->description(fn (DatenabgleichZeile $r) => trim(($r->ergebnis['nummer'] ? 'Mitgliedsnr. '.$r->ergebnis['nummer'].' · ' : '')
                        .($r->quelle === 'meldung' ? 'Meldung' : 'Anmeldung').' vom '.Carbon::parse($r->ergebnis['datum'] ?? $r->created_at)->format('d.m.Y'))),
                TextColumn::make('status')->label('Ergebnis')->badge()
                    ->formatStateUsing(fn (string $state) => DatenabgleichZeile::STATUS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'stimmt' => 'success', 'abweichung' => 'warning', default => 'danger'
                    }),
                TextColumn::make('felder')->label('Unterschiede (Meldung → NaMi)')->wrap()
                    ->state(fn (DatenabgleichZeile $r) => new HtmlString(collect($r->ergebnis['felder'] ?? [])
                        ->map(fn (array $f) => '<strong>'.e($f['feld']).':</strong> '.e($f['meldung']).' <span style="color:#888">→ NaMi: '.e($f['nami']).'</span>')->implode('<br>')))
                    ->placeholder('–'),
                TextColumn::make('erledigt_am')->label('In NaMi korrigiert')->date('d.m.Y')->placeholder('offen')
                    ->description(fn (DatenabgleichZeile $r) => $r->erlediger?->name),
            ])
            ->filters([
                SelectFilter::make('status')->label('Ergebnis')->options(DatenabgleichZeile::STATUS)->default('abweichung'),
                TernaryFilter::make('erledigt_am')->label('Korrigiert')->nullable()->trueLabel('ja')->falseLabel('offen'),
            ])
            ->recordActions([
                Action::make('erledigt')->label(fn (DatenabgleichZeile $r) => $r->erledigt_am ? 'Zurücknehmen' : 'In NaMi korrigiert')
                    ->icon(fn (DatenabgleichZeile $r) => $r->erledigt_am ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check')
                    ->color(fn (DatenabgleichZeile $r) => $r->erledigt_am ? 'gray' : 'success')
                    ->visible(fn (DatenabgleichZeile $r) => $r->status !== 'stimmt')
                    ->action(function (DatenabgleichZeile $r) {
                        abort_unless(static::canAccess(), 403);
                        $r->update($r->erledigt_am ? ['erledigt_am' => null, 'erledigt_von' => null] : ['erledigt_am' => now(), 'erledigt_von' => Filament::auth()->id()]);
                        AuditLogger::log('datenabgleich.erledigt', Filament::auth()->user(), DatenabgleichZeile::class, $r->id, request());
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListDatenabgleich::route('/')];
    }
}
