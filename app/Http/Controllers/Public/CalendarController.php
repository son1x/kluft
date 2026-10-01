<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kalender (Pflichtenheft 6.5, F-CAL-04): Liste und Monatsansicht aus dem
 * Termin-Cache – Nextcloud wird beim Seitenaufruf nie direkt abgefragt,
 * ein Ausfall dort beeinträchtigt die Seite also nicht (Abnahme 8).
 */
class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $calendars = Calendar::query()->where('active', true)->orderBy('label')->get();
        $kalender = $calendars->firstWhere('id', (int) $request->query('kalender'))?->id;
        $stufe = array_key_exists((string) $request->query('stufe'), config('dpsg.stages')) ? $request->query('stufe') : null;
        $ansicht = $request->query('ansicht') === 'monat' ? 'monat' : 'liste';

        $monat = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m', (string) $request->query('monat')), null, false)
            ?? CarbonImmutable::now()->startOfMonth();

        if ($ansicht === 'monat') {
            $from = $monat->startOfMonth()->startOfWeek();
            $to = $monat->endOfMonth()->endOfWeek();
        } else {
            $from = CarbonImmutable::now()->startOfDay();
            $to = $from->addMonths(6);
        }

        $events = $this->query($from, $to, $kalender, $stufe)->with('calendar')->orderBy('starts_at')->get();

        return view('calendar.index', [
            'calendars' => $calendars,
            'events' => $events,
            'kalender' => $kalender,
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

        $events = $this->query($from, $to, $request->integer('calendar') ?: null, null)
            ->with('calendar')->orderBy('starts_at')->limit(1000)->get()
            ->map(fn (CalendarEvent $e) => [
                'title' => $e->title,
                'start' => $e->starts_at->toIso8601String(),
                'end' => $e->ends_at?->toIso8601String(),
                'allDay' => $e->all_day,
                'location' => $e->location,
                'description' => $e->description,
                'calendar' => $e->calendar->label,
                'color' => $e->calendar->color,
            ]);

        return response()->json($events)->header('Cache-Control', 'public, max-age=300');
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
