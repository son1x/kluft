{{-- Chronik der Diözesanleitung als Zeitstrahl: Vorsitz links, Kuratinnen und Kuraten rechts, nach Jahrzehnten.
     Daten: config('dpsg.chronik.eintraege') (Instanz-Ordner, chronik.php). Ohne Daten bleibt der Bereich leer. --}}
@php
    $alle = collect(config('dpsg.chronik.eintraege', []));
    $zeitraum = fn (array $e) => $e['text'] ?? ($e['von'] ? ($e['bis'] ? $e['von'].'–'.$e['bis'] : 'seit '.$e['von']) : 'Zeitraum unbekannt');
    $mitJahr = $alle->whereNotNull('von')->sortByDesc('von');
    $jahrzehnte = $mitJahr->groupBy(fn (array $e) => intdiv($e['von'], 10) * 10);
    $ohneJahr = $alle->whereNull('von')->reject(fn (array $e) => $e['amt'] === 'stellv');
    $stellv = $alle->where('amt', 'stellv');
    $anzahl = ['vorsitz' => $alle->where('amt', 'vorsitz')->count(), 'kurat' => $alle->where('amt', 'kurat')->count()];
    $seit = $alle->whereNotNull('von')->min('von');
    $farbe = ['vorsitz' => ['rand' => 'border-dpsg-blau', 'punkt' => 'bg-dpsg-blau', 'chip' => 'bg-dpsg-blau/10 text-dpsg-blau'],
              'kurat' => ['rand' => 'border-dpsg-rot', 'punkt' => 'bg-dpsg-rot', 'chip' => 'bg-dpsg-rot/10 text-dpsg-rot'],
              'stellv' => ['rand' => 'border-dpsg-mittelbeige', 'punkt' => 'bg-dpsg-mittelbeige', 'chip' => 'bg-dpsg-hellbeige text-text-secondary']];
@endphp
@if ($alle->isNotEmpty())
<section class="mx-auto mb-16 max-w-5xl px-4" aria-labelledby="chronik-titel" x-data="{ filter: 'alle' }">
    <h2 id="chronik-titel" class="sr-only">Zeitstrahl der Diözesanleitung</h2>

    {{-- Auf einen Blick --}}
    <div class="grid grid-cols-3 gap-3 text-center">
        <div class="rounded-lg bg-dpsg-blau px-3 py-4 text-white shadow-md">
            <p class="font-serif text-3xl font-bold leading-none sm:text-4xl">{{ $seit }}</p>
            <p class="mt-1 text-xs uppercase tracking-wide text-white/80">erste Einträge</p>
        </div>
        <div class="rounded-lg bg-white px-3 py-4 shadow-md ring-1 ring-dpsg-mittelbeige">
            <p class="font-serif text-3xl font-bold leading-none text-dpsg-blau sm:text-4xl">{{ $anzahl['vorsitz'] }}</p>
            <p class="mt-1 text-xs uppercase tracking-wide text-text-secondary">im Vorsitz</p>
        </div>
        <div class="rounded-lg bg-white px-3 py-4 shadow-md ring-1 ring-dpsg-mittelbeige">
            <p class="font-serif text-3xl font-bold leading-none text-dpsg-rot sm:text-4xl">{{ $anzahl['kurat'] }}</p>
            <p class="mt-1 text-xs uppercase tracking-wide text-text-secondary">als Kurat*in</p>
        </div>
    </div>

    {{-- Filter (ohne JavaScript sind einfach alle sichtbar) --}}
    <div class="mt-8 flex flex-wrap items-center justify-center gap-2" role="group" aria-label="Anzeige einschränken">
        @foreach (['alle' => 'Alle', 'vorsitz' => 'Vorsitz', 'kurat' => 'Kuratinnen und Kuraten'] as $key => $label)
            <button type="button" x-on:click="filter = '{{ $key }}'" x-bind:aria-pressed="filter === '{{ $key }}'"
                    class="min-h-11 rounded-full border-2 border-dpsg-blau px-5 text-sm font-semibold transition"
                    x-bind:class="filter === '{{ $key }}' ? 'bg-dpsg-blau text-white' : 'bg-white text-dpsg-blau hover:bg-dpsg-hellbeige'">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Zeitstrahl --}}
    <div class="relative mt-10">
        <div class="absolute bottom-0 left-4 top-0 w-1 -translate-x-1/2 rounded bg-gradient-to-b from-dpsg-blau via-dpsg-mittelbeige to-dpsg-mittelbeige md:left-1/2" aria-hidden="true"></div>

        @foreach ($jahrzehnte as $jahrzehnt => $eintraege)
            <div class="relative mb-10">
                <h3 class="relative z-10 ms-0 w-fit rounded-full bg-dpsg-blau px-5 py-1.5 font-serif text-lg font-bold text-white shadow md:mx-auto">{{ $jahrzehnt }}er</h3>
                <ol class="mt-6 space-y-5">
                    @foreach ($eintraege as $e)
                        <li class="relative md:grid md:grid-cols-2 md:gap-x-16"
                            x-show="filter === 'alle' || filter === '{{ $e['amt'] }}'">
                            <div class="relative ms-10 rounded-lg border-l-4 bg-white p-4 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg md:ms-0 {{ $farbe[$e['amt']]['rand'] }} {{ $e['amt'] === 'kurat' ? 'md:col-start-2' : 'md:border-l-0 md:border-r-4 md:text-right' }}">
                                <span class="absolute top-5 -start-[2.1rem] size-4 rounded-full border-4 border-white shadow {{ $farbe[$e['amt']]['punkt'] }} {{ $e['amt'] === 'kurat' ? 'md:-start-[2.35rem]' : 'md:start-auto md:-end-[2.35rem]' }}" aria-hidden="true"></span>
                                <p class="text-xs font-semibold uppercase tracking-wide {{ $e['amt'] === 'kurat' ? 'text-dpsg-rot' : 'text-dpsg-blau' }}">{{ $e['titel'] }}</p>
                                <p class="font-serif text-xl font-bold leading-tight">{{ $e['name'] }}</p>
                                <p class="mt-2 flex flex-wrap items-center gap-2 {{ $e['amt'] === 'kurat' ? '' : 'md:justify-end' }}">
                                    <span class="rounded-full px-3 py-0.5 text-sm font-semibold {{ $farbe[$e['amt']]['chip'] }}">{{ $zeitraum($e) }}</span>
                                    @if ($e['aktuell'] ?? false)
                                        <span class="rounded-full bg-dpsg-yellow px-3 py-0.5 text-xs font-bold uppercase tracking-wide text-dpsg-blau">aktuell</span>
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>

    {{-- Zeitraum unbekannt und Stellvertretung: ohne Jahresangaben als Kacheln --}}
    @if ($ohneJahr->isNotEmpty() || $stellv->isNotEmpty())
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            @if ($ohneJahr->isNotEmpty())
                <div x-show="filter === 'alle' || {{ $ohneJahr->pluck('amt')->unique()->map(fn ($a) => "filter === '$a'")->implode(' || ') }}">
                    <h3 class="mb-3 font-serif text-lg font-bold text-dpsg-blau">Zeitraum nicht bekannt</h3>
                    <ul class="space-y-2">
                        @foreach ($ohneJahr as $e)
                            <li class="flex items-center justify-between gap-3 rounded-lg border-l-4 bg-white px-4 py-2 shadow-sm {{ $farbe[$e['amt']]['rand'] }}" x-show="filter === 'alle' || filter === '{{ $e['amt'] }}'">
                                <span class="font-semibold">{{ $e['name'] }}</span>
                                <span class="rounded-full px-3 py-0.5 text-xs font-semibold {{ $farbe[$e['amt']]['chip'] }}">{{ $e['titel'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($stellv->isNotEmpty())
                <div x-show="filter === 'alle'">
                    <h3 class="mb-3 font-serif text-lg font-bold text-dpsg-blau">Stellvertretender Vorstand</h3>
                    <ul class="space-y-2">
                        @foreach ($stellv as $e)
                            <li class="rounded-lg border-l-4 bg-white px-4 py-2 font-semibold shadow-sm {{ $farbe['stellv']['rand'] }}">{{ $e['name'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <p class="mt-10 rounded-lg bg-dpsg-hellbeige p-4 text-sm text-text-secondary">
        Die Liste ist, soweit sie sich rekonstruieren ließ. Fehlt jemand oder stimmt ein Zeitraum nicht? Bitte kurz beim Diözesanbüro melden.
        Die <a href="{{ url('/dioezesanleitung') }}" class="font-semibold text-dpsg-rot underline underline-offset-2 hover:no-underline">aktuelle Diözesanleitung</a> findest du auf einer eigenen Seite.
    </p>
</section>
@endif
