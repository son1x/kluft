<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Event;
use App\Support\CalendarItem;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Kalender (Pflichtenheft 6.5, F-CAL-04): Liste und Monatsansicht aus dem
 * Termin-Cache plus den Veranstaltungen aus dem Backend. Nextcloud wird
 * beim Seitenaufruf nie direkt abgefragt, ein Ausfall dort beeinträchtigt
 * die Seite also nicht (Abnahme 8).
 */
class CalendarController extends Controller
{
    /** Filterwert für „nur Veranstaltungen mit Anmeldung“. */
    private const EVENTS = 'veranstaltungen';

    /** Zeitraum der Listenansicht in Monaten. */
    private const LIST_MONTHS = 12;

    public function index(Request $request): View
    {
        $calendars = Calendar::query()->where('active', true)->orderBy('label')->get();
        $nurVeranstaltungen = $request->query('kalender') === self::EVENTS;
        $kalender = $nurVeranstaltungen ? null : $calendars->firstWhere('id', (int) $request->query('kalender'))?->id;
        $stufe = array_key_exists((string) $request->query('stufe'), config('dpsg.stages')) ? $request->query('stufe') : null;
        $ansicht = $request->query('ansicht') === 'monat' ? 'monat' : 'liste';

        $monat = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m', (string) $request->query('monat')), null, false)
            ?? CarbonImmutable::now()->startOfMonth();

        if ($ansicht === 'monat') {
            $from = $monat->startOfMonth()->startOfWeek();
            $to = $monat->endOfMonth()->endOfWeek();
        } else {
            $from = CarbonImmutable::now()->startOfDay();
            // Lager und Kurse stehen oft lange vorher fest: ein Jahr im Voraus.
            $to = $from->addMonths(self::LIST_MONTHS);
        }

        return view('calendar.index', [
            'calendars' => $calendars,
            'hasEvents' => Event::query()->visible()->exists(),
            'events' => $this->items($from, $to, $kalender, $stufe, $nurVeranstaltungen),
            'kalender' => $nurVeranstaltungen ? self::EVENTS : $kalender,
            'stufe' => $stufe,
            'ansicht' => $ansicht,
            'monat' => $monat,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $from = rescue(fn () => CarbonImmutable::parse((string) $request->query('from')), null, false) ?? CarbonImmutable::now()->startOfDay();
        $to = rescue(fn () => CarbonImmutable::parse((string) $request->query('to')), null, false) ?? $from->addMonths(3);
        $to = $to->min($from->addYear());

        $events = $this->items($from, $to, $request->integer('calendar') ?: null, null, false)
            ->take(1000)
            ->map(fn (CalendarItem $e) => [
                'title' => $e->title,
                'start' => $e->starts_at->toIso8601String(),
                'end' => $e->ends_at?->toIso8601String(),
                'allDay' => $e->all_day,
                'location' => $e->location,
                'description' => $e->description,
                'calendar' => $e->sourceLabel,
                'color' => $e->color,
                'url' => $e->url,
            ]);

        return response()->json($events->values())->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Nextcloud-Termine und Veranstaltungen aus dem Backend in einer Liste.
     * Steht eine Veranstaltung zusätzlich in der Nextcloud, erscheint sie
     * nur einmal – als Veranstaltung mit Anmeldung.
     *
     * @return Collection<int, CalendarItem>
     */
    private function items(CarbonImmutable $from, CarbonImmutable $to, ?int $kalender, ?string $stufe, bool $nurVeranstaltungen): Collection
    {
        $termine = $nurVeranstaltungen ? collect() : $this->query($from, $to, $kalender, $stufe)
            ->with('calendar')->orderBy('starts_at')->get()
            ->map(fn (CalendarEvent $e) => CalendarItem::fromCalendarEvent($e));

        $veranstaltungen = $kalender ? collect() : Event::query()->visible()
            ->when($stufe, fn (Builder $q) => $q->where('stage', $stufe))
            ->where('starts_at', '<', $to)
            ->where(fn (Builder $q) => $q->where('ends_at', '>', $from)->orWhere(fn (Builder $o) => $o->whereNull('ends_at')->where('starts_at', '>=', $from)))
            ->orderBy('starts_at')->get()
            ->map(fn (Event $e) => CalendarItem::fromEvent($e));

        return $veranstaltungen
            ->concat($termine->reject(fn (CalendarItem $t) => $veranstaltungen->contains(fn (CalendarItem $v) => $v->sameAs($t))))
            ->sortBy(fn (CalendarItem $i) => $i->starts_at->getTimestamp())
            ->values();
    }

    /**
     * Termine, die das Zeitfenster berühren (auch mehrtägige).
     *
     * @return Builder<CalendarEvent>
     */
    private function query(CarbonImmutable $from, CarbonImmutable $to, ?int $kalender, ?string $stufe): Builder
    {
        return CalendarEvent::query()
            ->whereHas('calendar', fn (Builder $c) => $c->where('active', true)
                ->when($kalender, fn (Builder $q) => $q->whereKey($kalender))
                ->when($stufe, fn (Builder $q) => $q->where('stage', $stufe)))
            ->where('starts_at', '<', $to)
            ->where(fn (Builder $q) => $q->where('ends_at', '>', $from)->orWhere(fn (Builder $o) => $o->whereNull('ends_at')->where('starts_at', '>=', $from)));
    }
}
