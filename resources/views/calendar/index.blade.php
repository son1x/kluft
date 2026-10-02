@extends('layouts.app')

@section('seo_title', 'Kalender – DPSG Diözesanverband Trier')
@section('seo_description', 'Termine des DPSG Diözesanverbands Trier, der Stufen und Arbeitskreise.')

@php
    $filter = array_filter(['kalender' => $kalender, 'stufe' => $stufe]);
    $timeLabel = function ($e) {
        if ($e->all_day) {
            $last = $e->lastDay();
            return $last && ! $last->isSameDay($e->starts_at)
                ? 'ganztägig bis '.$last->locale('de')->isoFormat('dd, D. MMM')
                : 'ganztägig';
        }
        $label = $e->starts_at->format('H:i');
        if ($e->ends_at) {
            $label .= $e->ends_at->isSameDay($e->starts_at)
                ? '–'.$e->ends_at->format('H:i')
                : ' bis '.$e->ends_at->locale('de')->isoFormat('dd, D. MMM, HH:mm');
        }
        return $label.' Uhr';
    };
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">Kalender</h1>
        <p class="mt-2 max-w-3xl text-text-secondary">Termine des Diözesanverbands, der Stufen und Arbeitskreise. <span class="font-bold text-dpsg-rot">Rot</span> markiert sind Veranstaltungen, zu denen du dich online anmelden kannst.</p>

        <form method="get" action="{{ route('calendar.index') }}" class="mt-6 flex flex-wrap items-end gap-4 bg-surface-muted p-4">
            <input type="hidden" name="ansicht" value="{{ $ansicht }}" />
            @if ($ansicht === 'monat')<input type="hidden" name="monat" value="{{ $monat->format('Y-m') }}" />@endif
            <label class="flex flex-col text-sm font-bold">
                Kalender
                <select name="kalender" class="mt-1 min-w-48 border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal">
                    <option value="">Alle Termine</option>
                    <option value="veranstaltungen" @selected($kalender === 'veranstaltungen')>Veranstaltungen mit Anmeldung</option>
                    @foreach ($calendars as $c)
                        <option value="{{ $c->id }}" @selected($kalender === $c->id)>{{ $c->label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col text-sm font-bold">
                Stufe
                <select name="stufe" class="mt-1 min-w-48 border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal">
                    <option value="">Alle Stufen</option>
                    @foreach (config('dpsg.stages') as $key => $s)
                        <option value="{{ $key }}" @selected($stufe === $key)>{{ $s['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="bg-dpsg-blau px-4 py-2 font-bold text-white hover:bg-dpsg-rot">Filtern</button>
            <div class="ml-auto flex" role="group" aria-label="Ansicht">
                <a href="{{ route('calendar.index', [...$filter, 'ansicht' => 'liste']) }}" @class(['border border-dpsg-blau px-4 py-2 font-bold', 'bg-dpsg-blau text-white' => $ansicht === 'liste', 'bg-white text-dpsg-blau' => $ansicht !== 'liste']) @if ($ansicht === 'liste') aria-current="page" @endif>Liste</a>
                <a href="{{ route('calendar.index', [...$filter, 'ansicht' => 'monat']) }}" @class(['border border-dpsg-blau px-4 py-2 font-bold', 'bg-dpsg-blau text-white' => $ansicht === 'monat', 'bg-white text-dpsg-blau' => $ansicht !== 'monat']) @if ($ansicht === 'monat') aria-current="page" @endif>Monat</a>
            </div>
        </form>

        @if ($calendars->isEmpty() && ! $hasEvents)
            <p class="mt-8 text-text-secondary">Der Kalender wird gerade eingerichtet. Bald findest du hier die Termine des Diözesanverbands und der Stufen.</p>
        @elseif ($ansicht === 'liste')
            @forelse ($events->groupBy(fn ($e) => $e->starts_at->format('Y-m')) as $ym => $monthEvents)
                <h2 class="mt-10 font-serif text-2xl font-bold">{{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $ym)->locale('de')->isoFormat('MMMM YYYY') }}</h2>
                <ul class="mt-4 space-y-4">
                    @foreach ($monthEvents as $e)
                        <li class="flex bg-white shadow-md">
                            <div class="flex w-24 shrink-0 flex-col items-center justify-center border-t-8 bg-surface-muted py-3 text-center" style="border-top-color: {{ e($e->color) }}">
                                <span class="text-xs text-text-secondary">{{ $e->starts_at->locale('de')->isoFormat('dddd') }}</span>
                                <span class="font-serif text-2xl font-bold text-dpsg-rot">{{ $e->starts_at->format('j') }}</span>
                                <span class="text-xs text-text-secondary">{{ $e->starts_at->locale('de')->isoFormat('MMM YYYY') }}</span>
                            </div>
                            <div class="min-w-0 flex-1 p-4">
                                <h3 @class(['font-serif text-lg font-bold', 'line-through' => $e->cancelled])>
                                    @if ($e->url)<a href="{{ $e->url }}" class="hover:text-dpsg-rot hover:underline">{{ $e->title }}</a>@else{{ $e->title }}@endif
                                </h3>
                                <p class="text-sm text-text-secondary">{{ $timeLabel($e) }}@if ($e->location) · {{ $e->location }}@endif</p>
                                <p class="mt-1 flex flex-wrap items-center gap-x-1 gap-y-1 text-xs">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ e($e->color) }}"></span>{{ $e->sourceLabel }}
                                    @if ($e->badge)<span @class(['ml-2 rounded-full px-2 py-0.5 font-bold', 'bg-dpsg-rot text-white' => ! $e->cancelled, 'bg-surface-muted text-text-secondary' => $e->cancelled])>{{ $e->badge }}</span>@endif
                                </p>
                                @if ($e->url)
                                    <p class="mt-2 text-sm"><a href="{{ $e->url }}" class="font-bold text-dpsg-rot underline">{{ $e->cancelled ? 'Mehr erfahren' : 'Infos und Anmeldung' }}</a></p>
                                @elseif ($e->description)
                                    <details class="mt-2 text-sm">
                                        <summary class="cursor-pointer text-dpsg-rot">Details</summary>
                                        <p class="mt-2 whitespace-pre-line">{{ $e->description }}</p>
                                    </details>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @empty
                <p class="mt-8 text-text-secondary">Keine Termine in den nächsten sechs Monaten.</p>
            @endforelse
        @else
            <div class="mt-8 flex items-center justify-between">
                <a href="{{ route('calendar.index', [...$filter, 'ansicht' => 'monat', 'monat' => $monat->subMonth()->format('Y-m')]) }}" class="border border-dpsg-rot px-3 py-1 text-dpsg-rot hover:bg-dpsg-rot hover:text-white">‹ <span class="sr-only">Vorheriger Monat</span></a>
                <h2 class="font-serif text-2xl font-bold">{{ $monat->locale('de')->isoFormat('MMMM YYYY') }}</h2>
                <a href="{{ route('calendar.index', [...$filter, 'ansicht' => 'monat', 'monat' => $monat->addMonth()->format('Y-m')]) }}" class="border border-dpsg-rot px-3 py-1 text-dpsg-rot hover:bg-dpsg-rot hover:text-white"><span class="sr-only">Nächster Monat</span> ›</a>
            </div>
            <div class="mt-4 grid grid-cols-7 border-l border-t border-dpsg-mittelbeige text-sm" role="grid" aria-label="Monatsansicht">
                @foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $wd)
                    <div class="border-b border-r border-dpsg-mittelbeige bg-surface-muted p-2 text-center font-bold" role="columnheader">{{ $wd }}</div>
                @endforeach
                @for ($day = $from; $day->lte($to); $day = $day->addDay())
                    @php($dayEvents = $events->filter(fn ($e) => $e->starts_at->lte($day->endOfDay()) && (($e->all_day ? $e->lastDay() : $e->ends_at) ?? $e->starts_at)->gte($day->startOfDay())))
                    <div @class(['min-h-24 border-b border-r border-dpsg-mittelbeige p-1', 'bg-white' => $day->month === $monat->month, 'bg-surface-muted/50 text-text-secondary' => $day->month !== $monat->month]) role="gridcell">
                        <div @class(['mb-1 text-right text-xs', 'font-bold text-dpsg-rot' => $day->isToday()])>{{ $day->format('j') }}</div>
                        @foreach ($dayEvents as $e)
                            @if ($e->url)
                                <a href="{{ $e->url }}" @class(['mb-1 block truncate rounded px-1 text-xs hover:underline', 'line-through' => $e->cancelled]) style="background: {{ e($e->color) }}; color: {{ $e->textColor() }}" title="{{ $e->title }} – {{ $timeLabel($e) }}@if ($e->badge) ({{ $e->badge }})@endif">
                                    @unless ($e->all_day){{ $e->starts_at->format('H:i') }} @endunless{{ $e->title }}
                                </a>
                            @else
                                <div class="mb-1 truncate rounded px-1 text-xs" style="background: {{ e($e->color) }}; color: {{ $e->textColor() }}" title="{{ $e->title }} – {{ $timeLabel($e) }}">
                                    @unless ($e->all_day){{ $e->starts_at->format('H:i') }} @endunless{{ $e->title }}
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endfor
            </div>
        @endif
    </div>
@endsection
