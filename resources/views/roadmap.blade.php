@extends('layouts.app')

@section('seo_title', 'Roadmap – '.config('dpsg.site.name'))
@section('seo_description', 'Umsetzungsstand der neuen Website des '.config('dpsg.site.name_genitive').'.')

@push('head')
    <meta name="robots" content="noindex, nofollow" />
@endpush

@php
    $statusLabels = \App\Support\Roadmap::STATUSES;
    $openTodos = collect($todos)->reject(fn ($t) => $t['done'] === true);
    $doneTodos = collect($todos)->filter(fn ($t) => $t['done'] === true);
    $total = array_sum($counts);
    $barColors = ['done' => 'bg-[#1e8e3e]', 'partial' => 'bg-[#f2b600]', 'open' => 'bg-[#d93025]', 'later' => 'bg-dpsg-mittelbeige', 'info' => 'bg-surface-muted'];
    $phaseLabel = collect($phases)->firstWhere(0, $currentPhase);
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">Roadmap</h1>
        <p class="mt-2 max-w-3xl text-text-secondary">Umsetzungsstand der neuen Website nach Pflichtenheft. Stand: {{ \Illuminate\Support\Carbon::parse($stand)->locale('de')->isoFormat('D. MMMM YYYY') }}.</p>

        @unless ($backup['healthy'])
            @include('partials.roadmap-backup-setup', ['backup' => $backup])
        @endunless

        {{-- Offene Punkte der Projektleitung --}}
        <section aria-labelledby="todo-heading" class="mt-8 border-l-4 border-dpsg-rot bg-dpsg-hellbeige p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="todo-heading" class="font-serif text-2xl font-bold">Deine offenen Punkte</h2>
                <p class="text-sm font-bold">{{ $doneTodos->count() }} von {{ count($todos) }} erledigt · aktueller Meilenstein: {{ $phaseLabel[0] }} – {{ $phaseLabel[1] }}</p>
            </div>
            <p class="mt-1 text-sm text-text-secondary">Diese Punkte kann nur die Projektleitung erledigen. Wo möglich, prüft die Seite automatisch, ob sie schon erledigt sind.</p>

            <ol class="mt-5 space-y-3">
                @foreach ($openTodos as $todo)
                    <li class="flex gap-4 bg-white p-4 shadow-sm">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-dpsg-rot" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">
                                {{ $todo['title'] }}
                                <span @class(['ml-2 inline-block rounded px-2 py-0.5 align-middle text-xs font-bold', 'bg-dpsg-blau text-white' => $todo['phase'] === $currentPhase, 'bg-surface-muted text-text' => $todo['phase'] !== $currentPhase])>Meilenstein {{ $todo['phase'] }}</span>
                            </p>
                            <p class="mt-1 text-sm">{{ $todo['detail'] }}</p>
                            <p class="mt-1 text-xs text-text-secondary">{{ $todo['done'] === false ? 'Automatisch geprüft: noch nicht erledigt.' : 'Wird nicht automatisch geprüft – gib Bescheid, wenn es erledigt ist.' }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($doneTodos->isNotEmpty())
                <details class="mt-4">
                    <summary class="cursor-pointer text-sm font-bold">Erledigt ({{ $doneTodos->count() }})</summary>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($doneTodos as $todo)
                            <li class="flex items-center gap-2"><x-heroicon-s-check-circle class="h-5 w-5 text-[#1e8e3e]" aria-hidden="true" /> <span class="line-through">{{ $todo['title'] }}</span></li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>

        {{-- Meilensteine (Pflichtenheft 12) --}}
        <section aria-labelledby="phasen-heading" class="mt-10">
            <h2 id="phasen-heading" class="font-serif text-2xl font-bold">Meilensteine</h2>
            <ol class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($phases as [$key, $label, $status])
                    <li @class(['flex items-center gap-3 bg-white p-3 shadow-sm', 'ring-2 ring-dpsg-blau' => $key === $currentPhase])>
                        @include('partials.ampel', ['status' => $status])
                        <span class="text-sm leading-tight">
                            <span class="block font-bold">{{ $key }}</span>
                            {{ $label }}
                            @if ($key === $currentPhase)<span class="block text-xs font-bold text-dpsg-blau">aktuell</span>@endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Rückmeldungen je Meilenstein (außerhalb des Pflichtenhefts) --}}
        @foreach ($feedback as $milestone => $items)
            @php($phase = collect($phases)->firstWhere(0, $milestone))
            <section aria-labelledby="feedback-{{ \Illuminate\Support\Str::slug($milestone) }}" class="mt-10 bg-white shadow-md">
                <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-surface-muted p-5">
                    <h2 id="feedback-{{ \Illuminate\Support\Str::slug($milestone) }}" class="font-serif text-xl font-bold">Meilenstein {{ $milestone }}@if ($phase && $phase[1] !== 'Feedback '.$milestone) – {{ $phase[1] }}@else: Rückmeldungen @endif</h2>
                    <p class="text-sm font-bold">{{ collect($items)->where('status', 'done')->count() }} von {{ count($items) }} umgesetzt</p>
                </header>
                <ul class="divide-y divide-surface-muted">
                    @foreach ($items as $item)
                        <li class="flex gap-4 px-5 py-3">
                            @include('partials.ampel', ['status' => $item['status']])
                            <div class="min-w-0">
                                <h3 class="font-bold">{{ $item['title'] }}</h3>
                                @if ($item['note'])<p class="mt-0.5 text-sm text-text-secondary">{{ $item['note'] }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        {{-- Gesamtstand --}}
        <section aria-labelledby="stand-heading" class="mt-10">
            <h2 id="stand-heading" class="font-serif text-2xl font-bold">Pflichtenheft im Überblick</h2>
            <div class="mt-4 flex h-4 overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
                @foreach ($counts as $status => $n)
                    @if ($n > 0)<span class="{{ $barColors[$status] }}" style="width: {{ $n / max(1, $total) * 100 }}%"></span>@endif
                @endforeach
            </div>
            <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                @foreach ($counts as $status => $n)
                    <li class="flex items-center gap-2">
                        @include('partials.ampel', ['status' => $status])
                        <span><strong>{{ $n }}</strong> {{ $statusLabels[$status] }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Abschnitte des Pflichtenhefts --}}
        <div class="mt-10 space-y-6">
            @foreach ($chapters as $chapter)
                <section aria-labelledby="kap-{{ $chapter['num'] }}" class="bg-white shadow-md">
                    <header class="flex items-center gap-4 border-b border-surface-muted p-5">
                        @include('partials.ampel', ['status' => $chapter['status']])
                        <h2 id="kap-{{ $chapter['num'] }}" class="font-serif text-xl font-bold">{{ $chapter['num'] }}. {{ $chapter['title'] }}</h2>
                    </header>

                    @if ($chapter['note'])
                        <p class="px-5 pt-4 text-sm text-text-secondary">{{ $chapter['note'] }}</p>
                    @endif

                    @if ($chapter['children'])
                        <ul class="divide-y divide-surface-muted">
                            @foreach ($chapter['children'] as $section)
                                <li class="flex gap-4 px-5 py-3">
                                    @include('partials.ampel', ['status' => $section['status']])
                                    <div class="min-w-0">
                                        <h3 class="font-bold">{{ $section['num'] }} {{ $section['title'] }}</h3>
                                        @if ($section['note'])<p class="mt-0.5 text-sm text-text-secondary">{{ $section['note'] }}</p>@endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($chapter['num'] === '12')
                        <p class="p-5 text-sm text-text-secondary">Siehe „Meilensteine“ oben.</p>
                    @elseif ($chapter['num'] === '13')
                        <ol class="divide-y divide-surface-muted">
                            @foreach ($acceptance as $i => [$status, $text])
                                <li class="flex items-center gap-4 px-5 py-2.5">
                                    @include('partials.ampel', ['status' => $status])
                                    <span class="text-sm"><span class="font-bold">{{ $i + 1 }}.</span> {{ $text }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <div class="pb-1"></div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
@endsection
