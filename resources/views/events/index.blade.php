@extends('layouts.app')

@section('seo_title', 'Veranstaltungen – '.config('dpsg.site.name'))
@section('seo_description', 'Lager, Kurse und Aktionen des '.config('dpsg.site.name_genitive').' mit Online-Anmeldung.')

@php
    $stateBadge = [
        'offen' => ['Anmeldung offen', 'bg-stufe-pfadi text-white'],
        'warteliste' => ['Warteliste', 'bg-dpsg-yellow text-text'],
        'ausgebucht' => ['Ausgebucht', 'bg-dpsg-mittelbeige text-text'],
        'bald' => ['Anmeldung bald', 'bg-surface-muted text-text'],
        'geschlossen' => ['Anmeldung geschlossen', 'bg-surface-muted text-text'],
        'abgesagt' => ['Abgesagt', 'bg-dpsg-rot text-white'],
    ];
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">Veranstaltungen</h1>
        <p class="mt-2 max-w-3xl text-text-secondary">Lager, Kurse, Aktionen und Versammlungen im {{ config('dpsg.site.level') }} – mit Online-Anmeldung. Weitere Termine stehen im <a href="{{ route('calendar.index') }}" class="text-dpsg-rot underline">Kalender</a>.</p>

        <form method="get" action="{{ route('events.index') }}" class="mt-6 flex flex-wrap items-end gap-4 bg-surface-muted p-4">
            <label class="flex flex-col text-sm font-bold">
                Stufe
                <select name="stufe" class="mt-1 min-w-48 border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal">
                    <option value="">Alle Veranstaltungen</option>
                    @foreach (config('dpsg.stages') as $key => $s)
                        <option value="{{ $key }}" @selected($stufe === $key)>{{ $s['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="bg-dpsg-blau px-4 py-2 font-bold text-white hover:bg-dpsg-rot">Filtern</button>
        </form>

        @if ($events->isEmpty())
            <p class="mt-8 text-text-secondary">Gerade sind keine Veranstaltungen ausgeschrieben. Schau bald wieder vorbei!</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($events as $event)
                    @php([$badge, $badgeClass] = $stateBadge[$event->registrationState()])
                    <li>
                        <a href="{{ route('events.show', $event->slug) }}" class="group flex h-full flex-col bg-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                            <div @class([
                                'relative aspect-[16/9] overflow-hidden',
                                'bg-dpsg-blau' => ! $event->stage,
                                'bg-stufe-biber' => $event->stage === 'biber',
                                'bg-stufe-woelflinge' => $event->stage === 'woelflinge',
                                'bg-stufe-jupfi' => $event->stage === 'jupfi',
                                'bg-stufe-pfadi' => $event->stage === 'pfadi',
                                'bg-stufe-rover' => $event->stage === 'rover',
                            ])>
                                @if ($event->coverMedia)
                                    <x-picture :media="$event->coverMedia" alt="" sizes="(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw" class="h-full w-full object-cover" />
                                @elseif ($event->stage && ($mascot = config("dpsg.stages.{$event->stage}.mascot")))
                                    <img src="{{ asset($mascot) }}" alt="" class="absolute -bottom-4 right-4 h-4/5 opacity-90" loading="lazy" />
                                @else
                                    <x-heroicon-o-calendar-days class="absolute bottom-4 right-6 h-24 w-24 text-white/30" aria-hidden="true" />
                                @endif
                                <span class="absolute left-0 top-4 bg-white px-3 py-2 text-center leading-none shadow">
                                    <span class="block text-2xl font-bold text-dpsg-rot">{{ $event->starts_at->format('d') }}</span>
                                    <span class="block text-xs font-bold uppercase">{{ $event->starts_at->locale('de')->isoFormat('MMM YY') }}</span>
                                </span>
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <div class="flex flex-wrap gap-2 text-xs font-bold">
                                    @if ($event->stage)
                                        @include('partials.stufe-badge', ['stage' => $event->stage])
                                    @else
                                        <span class="bg-dpsg-hellbeige px-2 py-1">{{ $event->scopeLabel() }}</span>
                                    @endif
                                    <span class="{{ $badgeClass }} px-2 py-1">{{ $badge }}</span>
                                </div>
                                <h2 class="mt-3 font-serif text-xl font-bold group-hover:text-dpsg-rot">{{ $event->title }}</h2>
                                <p class="mt-1 text-sm text-text-secondary">{{ $event->dateLabel() }}@if ($event->location) · {{ $event->location }}@endif</p>
                                @if ($event->teaser)
                                    <p class="mt-3 text-sm">{{ $event->teaser }}</p>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
