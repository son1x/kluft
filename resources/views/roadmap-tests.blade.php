@extends('layouts.app')

@section('seo_title', 'Tests – Roadmap – DPSG Diözesanverband Trier')

@push('head')
    <meta name="robots" content="noindex, nofollow" />
@endpush

@php
    $totals = $report['totals'] ?? null;
    $allGreen = $totals && $totals['failed'] === 0;
    $generated = isset($report['generated_at']) ? \Illuminate\Support\Carbon::parse($report['generated_at'])->timezone('Europe/Berlin')->locale('de') : null;
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <a href="{{ route('roadmap') }}" class="inline-flex items-center gap-1 text-sm font-bold text-dpsg-rot hover:underline">
            <x-heroicon-o-arrow-left class="h-4 w-4" aria-hidden="true" /> Roadmap
        </a>
        <h1 class="mt-2 font-serif text-3xl font-bold">Automatische Tests</h1>
        <p class="mt-2 max-w-3xl text-text-secondary">
            Bei jeder Auslieferung prüft die komplette Testsuite, ob Funktionen, Rechte und Datenschutzregeln noch wie im Pflichtenheft verlangt arbeiten.
            Schlägt auch nur ein Test fehl, wird die neue Version nicht ausgeliefert – diese Seite zeigt deshalb das Ergebnis der Version, die gerade läuft.
        </p>

        @if (! $report)
            <p class="mt-8 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-4">Noch kein Testbericht vorhanden. Er entsteht bei der nächsten Auslieferung.</p>
        @else
            {{-- Ergebnis --}}
            <section aria-label="Ergebnis" @class([
                'mt-8 flex flex-col gap-6 rounded-lg p-6 text-white shadow-md md:flex-row md:items-center md:justify-between',
                'bg-[#1e7a36]' => $allGreen,
                'bg-[#b3261e]' => ! $allGreen,
            ])>
                <div class="flex items-center gap-4">
                    @if ($allGreen)
                        <x-heroicon-s-check-circle class="h-14 w-14 shrink-0" aria-hidden="true" />
                    @else
                        <x-heroicon-s-x-circle class="h-14 w-14 shrink-0" aria-hidden="true" />
                    @endif
                    <div>
                        <p class="font-serif text-2xl font-bold">
                            {{ $allGreen ? 'Alle '.$totals['tests'].' Tests bestanden' : $totals['failed'].' von '.$totals['tests'].' Tests fehlgeschlagen' }}
                        </p>
                        @if ($generated)
                            <p class="text-sm text-white/85">
                                Stand {{ $generated->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr
                                @if ($report['commit'])
                                    · Version
                                    @if ($report['commit_url'])<a href="{{ $report['commit_url'] }}" class="font-mono underline" target="_blank" rel="noopener">{{ $report['commit'] }}</a>@else<span class="font-mono">{{ $report['commit'] }}</span>@endif
                                @endif
                                @if ($report['commit_message']) – „{{ $report['commit_message'] }}“@endif
                            </p>
                        @endif
                    </div>
                </div>
                <dl class="grid shrink-0 grid-cols-3 gap-6 text-center">
                    <div><dt class="text-xs uppercase tracking-wide text-white/80">Tests</dt><dd class="font-serif text-2xl font-bold">{{ $totals['tests'] }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-white/80">Prüfungen</dt><dd class="font-serif text-2xl font-bold">{{ number_format($totals['assertions'], 0, ',', '.') }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-white/80">Dauer</dt><dd class="font-serif text-2xl font-bold">{{ number_format($totals['time'], 0, ',', '.') }} s</dd></div>
                </dl>
            </section>
            @if ($report['run_url'])
                <p class="mt-2 text-right text-xs text-text-secondary"><a href="{{ $report['run_url'] }}" target="_blank" rel="noopener" class="underline">Protokoll des Durchlaufs auf GitHub</a></p>
            @endif

            {{-- Bereiche --}}
            <nav aria-label="Bereiche" class="mt-8 flex flex-wrap gap-2">
                @foreach ($areas as $key => $area)
                    <a href="#bereich-{{ \Illuminate\Support\Str::slug($key) }}" class="inline-flex items-center gap-2 rounded-full border border-dpsg-mittelbeige bg-white px-3 py-1 text-sm font-bold hover:border-dpsg-blau">
                        <span @class(['h-2.5 w-2.5 rounded-full', 'bg-[#1e7a36]' => $area['passed'] === $area['total'], 'bg-[#b3261e]' => $area['passed'] !== $area['total']]) aria-hidden="true"></span>
                        {{ $area['label'] }} <span class="font-normal text-text-secondary">{{ $area['total'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-8 space-y-10">
                @foreach ($areas as $key => $area)
                    <section id="bereich-{{ \Illuminate\Support\Str::slug($key) }}" aria-labelledby="h-{{ \Illuminate\Support\Str::slug($key) }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b-2 border-dpsg-blau pb-2">
                            <h2 id="h-{{ \Illuminate\Support\Str::slug($key) }}" class="font-serif text-2xl font-bold">{{ $area['label'] }}</h2>
                            <p @class(['text-sm font-bold', 'text-[#1e7a36]' => $area['passed'] === $area['total'], 'text-[#b3261e]' => $area['passed'] !== $area['total']])>
                                {{ $area['passed'] }} / {{ $area['total'] }} bestanden
                            </p>
                        </div>

                        @foreach ($area['classes'] as $class)
                            <div class="mt-5">
                                @if ($class['description'])
                                    <p class="text-sm text-text-secondary">{{ $class['description'] }}</p>
                                @endif
                                <ul class="mt-2 divide-y divide-surface-muted rounded-lg bg-white shadow-sm">
                                    @foreach ($class['tests'] as $test)
                                        <li class="flex items-start gap-3 px-4 py-2.5">
                                            @if ($test['status'] === 'passed')
                                                <x-heroicon-s-check-circle class="mt-0.5 h-5 w-5 shrink-0 text-[#1e7a36]" aria-label="bestanden" />
                                            @elseif ($test['status'] === 'skipped')
                                                <x-heroicon-s-minus-circle class="mt-0.5 h-5 w-5 shrink-0 text-dpsg-mittelbeige" aria-label="übersprungen" />
                                            @else
                                                <x-heroicon-s-x-circle class="mt-0.5 h-5 w-5 shrink-0 text-[#b3261e]" aria-label="fehlgeschlagen" />
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <p>{{ $test['name'] }}</p>
                                                @if ($test['message'])
                                                    <p class="mt-0.5 text-sm text-[#b3261e]">{{ $test['message'] }}</p>
                                                @endif
                                            </div>
                                            <span class="hidden shrink-0 text-xs text-text-secondary sm:block" title="{{ $test['method'] }}">{{ $test['assertions'] }} {{ $test['assertions'] === 1 ? 'Prüfung' : 'Prüfungen' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
