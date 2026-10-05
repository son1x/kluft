<?php

namespace App\Filament\Resources\CalendarEntries;

use App\Filament\Resources\CalendarEntries\Pages\ListCalendarEntries;
use App\Filament\Resources\Events\EventResource;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Nextcloud-Termine im Backend: zeigt, zu welchen Terminen es schon eine
 * Veranstaltung mit Anmeldung gibt, und legt sie bei Bedarf vorausgefüllt
 * an. Nur lesend – die Termine selbst werden in der Nextcloud gepflegt.
 */
class CalendarEntryResource extends Resource
{
    protected static ?string $model = CalendarEvent::class;

    protected static ?string $slug = 'kalendertermine';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Termine & Veranstaltungen';

    protected static ?string $modelLabel = 'Kalendertermin';

    protected static ?string $pluralModelLabel = 'Kalendertermine';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('calendar')
            ->whereHas('calendar', fn (Builder $c) => $c->where('active', true))
            ->where(fn (Builder $q) => $q->where('ends_at', '>=', now())
                ->orWhere(fn (Builder $o) => $o->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay())));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')->label('Datum')->sortable()
                    ->formatStateUsing(fn (CalendarEvent $record) => $record->starts_at->locale('de')->isoFormat('dd, D. MMM YYYY')
                        .($record->all_day ? '' : ', '.$record->starts_at->format('H:i').' Uhr')),
                TextColumn::make('title')->label('Termin')->searchable()->weight('bold')->wrap()
                    ->description(fn (CalendarEvent $record) => $record->location),
                TextColumn::make('calendar.label')->label('Kalender')
                    ->badge()->color('gray'),
                TextColumn::make('anmeldung')->label('Anmeldung')
                    ->state(fn (CalendarEvent $record) => self::matching($record)
                        ? (self::matching($record)->status === 'draft' ? 'Entwurf' : 'vorhanden')
                        : 'keine')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'vorhanden' => 'success',
                        'Entwurf' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('calendar_id')->label('Kalender')
                    ->options(fn () => Calendar::query()->where('active', true)->orderBy('label')->pluck('label', 'id')),
                TernaryFilter::make('hat_anmeldung')->label('Anmeldung')
                    ->placeholder('Alle')->trueLabel('Mit Anmeldung')->falseLabel('Ohne Anmeldung')
                    ->queries(
                        true: fn (Builder $q) => $q->whereIn('id', self::idsWithEvent($q)),
                        false: fn (Builder $q) => $q->whereNotIn('id', self::idsWithEvent($q)),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->recordActions([
                Action::make('anlegen')->label('Anmeldung anlegen')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->button()
                    ->visible(fn (CalendarEvent $record) => ! self::matching($record) && EventResource::canCreate())
                    ->url(fn (CalendarEvent $record) => EventResource::getUrl('create', ['kalendertermin' => $record->id])),
                Action::make('oeffnen')->label('Anmeldung öffnen')
                    ->icon(Heroicon::OutlinedTicket)
                    ->link()
                    ->visible(fn (CalendarEvent $record) => ($event = self::matching($record)) && EventResource::canEdit($event))
                    ->url(fn (CalendarEvent $record) => EventResource::getUrl('edit', ['record' => self::matching($record)])),
            ])
            ->recordUrl(null)
            ->emptyStateHeading('Keine kommenden Termine')
            ->emptyStateDescription('Unter „Kalender“ lassen sich Nextcloud-Kalender eintragen; ihre Termine erscheinen dann hier.');
    }

    /** Je Termin nur einmal suchen (Spalte und Aktionen fragen mehrfach). */
    private static function matching(CalendarEvent $record): ?Event
    {
        // WeakMap: gilt nur, solange dieses Datensatz-Objekt lebt (also für
        // eine Anfrage), damit nie ein veralteter Treffer angezeigt wird.
        static $cache = new \WeakMap;
        if (! $cache->offsetExists($record)) {
            $cache[$record] = $record->matchingEvent();
        }

        return $cache[$record];
    }

    /** @return list<int> */
    private static function idsWithEvent(Builder $query): array
    {
        return (clone $query)->get()->filter(fn (CalendarEvent $e) => $e->matchingEvent() !== null)->pluck('id')->all();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalendarEntries::route('/'),
        ];
    }
}
