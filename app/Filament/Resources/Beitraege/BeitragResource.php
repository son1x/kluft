<?php

namespace App\Filament\Resources\Beitraege;

use App\Filament\Resources\Beitraege\Pages\ListBeitraege;
use App\Models\Beitrag;
use App\Models\BeitragsMitglied;
use App\Support\AuditLogger;
use App\Support\Beitraege;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Mitgliedsbeiträge des Stamms je Halbjahr: wer hat bezahlt, wer nicht
 * (docs/beitraege.md). Mitglieder und Beitragsart kommen aus NaMi,
 * Zahlungen hakt die Kasse von Hand ab.
 */
class BeitragResource extends Resource
{
    protected static ?string $model = Beitrag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Kasse';

    protected static ?string $slug = 'kasse/beitraege';

    protected static ?string $navigationLabel = 'Mitgliedsbeiträge';

    protected static ?string $modelLabel = 'Beitrag';

    protected static ?string $pluralModelLabel = 'Mitgliedsbeiträge';

    protected static ?int $navigationSort = 1;

    /** Betrag in Euro, mit Komma oder Punkt. */
    public const EURO = ['nullable', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'];

    public static function canViewAny(): bool
    {
        return Beitraege::zugriff(Filament::auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $euro = fn (?int $state) => Beitraege::euro($state);
        $nachname = fn () => BeitragsMitglied::query()->select('nachname')->whereColumn('beitrags_mitglieder.id', 'beitraege.beitrags_mitglied_id');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('mitglied'))
            ->defaultSort(fn (Builder $query) => $query->orderBy($nachname()))
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->emptyStateHeading('Noch keine Beiträge für dieses Halbjahr')
            ->emptyStateDescription(function () {
                $vorhanden = Beitrag::query()->distinct()->orderByDesc('halbjahr')->pluck('halbjahr')->map(fn ($h) => Beitraege::label((string) $h))->all();

                return $vorhanden === []
                    ? 'Oben „Aus NaMi abrufen“ holt die Mitglieder und legt die Beiträge des Halbjahres an.'
                    : 'Beiträge gibt es für: '.implode(', ', $vorhanden).' (Filter „Halbjahr“). Oben „Halbjahr anlegen“ legt dieses Halbjahr ohne NaMi-Anmeldung an.';
            })
            ->columns([
                TextColumn::make('name')->label('Name')
                    ->state(fn (Beitrag $record) => $record->mitglied?->name())
                    ->description(fn (Beitrag $record) => trim(($record->mitglied?->mitgliedsnummer ? 'Nr. '.$record->mitglied->mitgliedsnummer : '')
                        .($record->mitglied?->schnupper ? ' · Schnuppermitglied' : '')
                        .($record->mitglied && ! $record->mitglied->aktiv ? ' · nicht mehr in NaMi' : '')
                        .($record->mitglied?->austritt ? ' · Austritt '.$record->mitglied->austritt->format('d.m.Y') : ''), ' ·'))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy($nachname(), $direction))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('mitglied', fn (Builder $q) => $q
                        ->where('nachname', 'like', "%{$search}%")->orWhere('vorname', 'like', "%{$search}%")->orWhere('mitgliedsnummer', 'like', "%{$search}%"))),
                TextColumn::make('beitragsart')->label('Beitragsart')->placeholder('fehlt')->wrap()
                    ->color(fn (Beitrag $record) => $record->beitragsart === null ? 'danger' : null)
                    ->description(fn (Beitrag $record) => match (true) {
                        (bool) $record->mitglied?->beitragsart_manuell => 'von Hand gesetzt',
                        $record->beitragsart === null && $record->mitglied?->details_verweigert => 'NaMi verweigert die Details',
                        default => null,
                    }),
                TextColumn::make('soll_cent')->label('Soll')->formatStateUsing($euro)->alignEnd()
                    ->description(fn (Beitrag $record) => $record->bundes_cent !== null ? 'Bund '.Beitraege::euro($record->bundes_cent) : null)
                    ->summarize(Sum::make()->label('Soll')->formatStateUsing(fn ($state) => Beitraege::euro((int) $state))),
                TextColumn::make('bundes_cent')->label('Bundesbeitrag')->formatStateUsing($euro)->placeholder('–')->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()->label('An den Bundesverband')->formatStateUsing(fn ($state) => Beitraege::euro((int) $state))),
                TextColumn::make('bezahlt_cent')->label('Bezahlt')->formatStateUsing($euro)->placeholder('–')->alignEnd()
                    ->summarize(Sum::make()->label('Eingegangen')->formatStateUsing(fn ($state) => Beitraege::euro((int) $state))),
                TextColumn::make('offen')->label('Offen')->alignEnd()
                    ->state(fn (Beitrag $record) => in_array($record->status(), ['bezahlt', 'frei'], true) ? 0 : max(0, $record->soll_cent - (int) $record->bezahlt_cent))
                    ->formatStateUsing(fn (int $state) => $state > 0 ? Beitraege::euro($state) : '–')
                    ->summarize(Summarizer::make()->label('Offen')
                        ->using(fn (QueryBuilder $query) => (int) $query->where(fn (QueryBuilder $q) => $q->whereNull('zahlart')->orWhere('zahlart', '!=', 'erlassen'))
                            ->sum(DB::raw('CASE WHEN soll_cent > COALESCE(bezahlt_cent, 0) THEN soll_cent - COALESCE(bezahlt_cent, 0) ELSE 0 END')))
                        ->formatStateUsing(fn ($state) => Beitraege::euro((int) $state))),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Beitrag $record) => $record->status())
                    ->formatStateUsing(fn (string $state, Beitrag $record) => match (true) {
                        $record->zahlart === 'erlassen' => 'erlassen',
                        $state === 'frei' => 'nichts zu zahlen',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'bezahlt' => 'success', 'teilweise' => 'warning', 'frei' => 'gray', default => 'danger',
                    }),
                TextColumn::make('bezahlt_am')->label('Bezahlt am')->date('d.m.Y')->placeholder('–')->sortable()
                    ->description(fn (Beitrag $record) => Beitrag::ZAHLARTEN[$record->zahlart ?? ''] ?? null),
                TextColumn::make('notiz')->label('Notiz')->placeholder('–')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('halbjahr')->label('Halbjahr')
                    ->options(fn () => Beitraege::auswahl(Beitrag::query()->distinct()->pluck('halbjahr')->all()))
                    ->default(Beitraege::aktuell()),
                SelectFilter::make('stand')->label('Status')
                    ->options(['offen' => 'offen oder teilweise', 'bezahlt' => 'bezahlt oder erlassen'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'offen' => $query->whereIn('beitraege.id', Beitrag::query()->offen()->select('id')),
                        'bezahlt' => $query->whereNotIn('beitraege.id', Beitrag::query()->offen()->select('id')),
                        default => $query,
                    }),
                SelectFilter::make('art')->label('Mitgliedschaft')
                    ->options(['mitglied' => 'Mitglieder', 'schnupper' => 'Schnuppermitglieder'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'mitglied' => $query->whereRelation('mitglied', 'schnupper', false),
                        'schnupper' => $query->whereRelation('mitglied', 'schnupper', true),
                        default => $query,
                    }),
                SelectFilter::make('beitragsart')->label('Beitragsart')
                    ->options(fn () => ['__fehlt' => 'Beitragsart fehlt'] + Beitrag::query()->whereNotNull('beitragsart')->distinct()->orderBy('beitragsart')->pluck('beitragsart', 'beitragsart')->all())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        '__fehlt' => $query->whereNull('beitraege.beitragsart'),
                        default => $query->where('beitraege.beitragsart', $data['value']),
                    }),
            ])
            ->recordActions([
                Action::make('zahlung')
                    ->label('Zahlung')
                    ->icon('heroicon-o-check-circle')
                    ->color(fn (Beitrag $record) => $record->status() === 'bezahlt' ? 'gray' : 'success')
                    ->modalHeading(fn (Beitrag $record) => 'Zahlung – '.$record->mitglied?->name())
                    ->fillForm(fn (Beitrag $record) => [
                        'soll' => self::alsEuro($record->soll_cent),
                        'bezahlt' => self::alsEuro($record->bezahlt_cent ?? $record->soll_cent),
                        'bezahlt_am' => $record->bezahlt_am ?? now(),
                        'zahlart' => $record->zahlart ?? 'ueberweisung',
                        'notiz' => $record->notiz,
                    ])
                    ->schema([
                        TextInput::make('soll')->label('Soll')->suffix('€')->required()->rules(self::EURO)->inputMode('decimal')
                            ->helperText('Aus dem Beitragssatz der Beitragsart; hier nur für diesen Beitrag änderbar.'),
                        TextInput::make('bezahlt')->label('Bezahlt')->suffix('€')->rules(self::EURO)->inputMode('decimal'),
                        DatePicker::make('bezahlt_am')->label('Bezahlt am'),
                        Select::make('zahlart')->label('Zahlart')->options(Beitrag::ZAHLARTEN),
                        TextInput::make('notiz')->label('Notiz')->maxLength(255),
                    ])
                    ->action(function (Beitrag $record, array $data) {
                        abort_unless(static::canViewAny(), 403);
                        $record->fill([
                            'soll_cent' => self::alsCent($data['soll']) ?? $record->soll_cent,
                            'bezahlt_cent' => self::alsCent($data['bezahlt'] ?? null),
                            'bezahlt_am' => $data['bezahlt_am'] ?: null,
                            'zahlart' => $data['zahlart'] ?: null,
                            'notiz' => $data['notiz'] ?: null,
                            'erfasst_von' => Filament::auth()->id(),
                        ])->save();
                        AuditLogger::log('beitrag.zahlung', Filament::auth()->user(), Beitrag::class, $record->id, request());
                        Notification::make()->title('Gespeichert: '.$record->status())->success()->send();
                    }),
                Action::make('beitragsart')
                    ->label('Beitragsart')
                    ->icon('heroicon-o-tag')
                    ->color('warning')
                    ->visible(fn (Beitrag $record) => $record->mitglied !== null && ! $record->mitglied->schnupper
                        && ($record->mitglied->beitragsart === null || $record->mitglied->beitragsart_manuell))
                    ->modalHeading(fn (Beitrag $record) => 'Beitragsart – '.$record->mitglied?->name())
                    ->modalDescription('Für Personen, deren Details NaMi nicht herausgibt. Gilt für die Person und ihre offenen Beiträge; liefert NaMi später selbst eine Beitragsart, gilt wieder die aus NaMi.')
                    ->fillForm(fn (Beitrag $record) => ['art' => $record->mitglied?->beitragsart])
                    ->schema([
                        Select::make('art')->label('Beitragsart')->required()
                            ->options(fn () => array_combine(Beitraege::arten(), Beitraege::arten())),
                    ])
                    ->action(function (Beitrag $record, array $data) {
                        abort_unless(static::canViewAny(), 403);
                        abort_unless(in_array($data['art'], Beitraege::arten(), true), 422);
                        $angepasst = $record->mitglied?->beitragsartSetzen((string) $data['art']) ?? 0;
                        AuditLogger::log('beitrag.beitragsart', Filament::auth()->user(), BeitragsMitglied::class, $record->beitrags_mitglied_id, request());
                        Notification::make()->title("Beitragsart gesetzt, {$angepasst} offene Beiträge angepasst.")->success()->send();
                    }),
                Action::make('zuruecksetzen')
                    ->label('Zurücksetzen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (Beitrag $record) => $record->bezahlt_am !== null || $record->bezahlt_cent !== null || $record->zahlart !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Die erfasste Zahlung wird entfernt; der Beitrag ist wieder offen.')
                    ->action(function (Beitrag $record) {
                        abort_unless(static::canViewAny(), 403);
                        $record->fill(['bezahlt_cent' => null, 'bezahlt_am' => null, 'zahlart' => null, 'erfasst_von' => Filament::auth()->id()])->save();
                        AuditLogger::log('beitrag.zurueckgesetzt', Filament::auth()->user(), Beitrag::class, $record->id, request());
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('bezahlt')
                    ->label('Als bezahlt markieren')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->schema([
                        DatePicker::make('bezahlt_am')->label('Bezahlt am')->default(now())->required(),
                        Select::make('zahlart')->label('Zahlart')->options(Beitrag::ZAHLARTEN)->default('lastschrift')->required(),
                    ])
                    ->modalDescription('Bei allen ausgewählten offenen Beiträgen wird der volle Betrag als bezahlt eingetragen. Bereits bezahlte bleiben, wie sie sind.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, array $data) {
                        abort_unless(static::canViewAny(), 403);
                        $n = 0;
                        foreach ($records as $record) {
                            if ($record instanceof Beitrag && in_array($record->status(), ['offen', 'teilweise'], true)) {
                                $record->fill(['bezahlt_cent' => $record->soll_cent, 'bezahlt_am' => $data['bezahlt_am'], 'zahlart' => $data['zahlart'], 'erfasst_von' => Filament::auth()->id()])->save();
                                $n++;
                            }
                        }
                        AuditLogger::log('beitrag.zahlung_sammel:'.$n, Filament::auth()->user(), request: request());
                        Notification::make()->title("{$n} Beiträge als bezahlt markiert.")->success()->send();
                    }),
            ]);
    }

    public static function alsEuro(?int $cent): ?string
    {
        return $cent === null ? null : number_format($cent / 100, 2, ',', '');
    }

    public static function alsCent(mixed $euro): ?int
    {
        if ($euro === null || $euro === '') {
            return null;
        }

        return (int) round((float) str_replace(',', '.', (string) $euro) * 100);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBeitraege::route('/'),
        ];
    }
}
