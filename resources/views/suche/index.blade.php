@extends('layouts.app')

@section('seo_title', 'Suche – '.config('dpsg.site.name'))

@push('head')
    <meta name="robots" content="noindex, follow" />
@endpush

{{-- Suche (docs/plan-ehrenamt.md Nr. 3) nach Apple HIG: Suchfeld mit Lupe und
     Löschen-Knopf (type=search), kurzer Platzhalter, Bereichsleiste mit „Alle“
     als Standard, Treffer nach Relevanz mit Markierung, hilfreicher Leerzustand,
     Tippflächen mindestens 44 px. --}}
@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10 font-sans">
        <h1 class="font-serif text-3xl font-bold">Suche</h1>

        <form method="get" action="{{ route('suche') }}" role="search" class="mt-6">
            <div class="flex items-center gap-2 rounded-full bg-white px-4 shadow-sm ring-1 ring-dpsg-mittelbeige focus-within:ring-2 focus-within:ring-dpsg-blau">
                <svg class="h-5 w-5 shrink-0 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <label for="suche-q" class="sr-only">Suchbegriff</label>
                <input id="suche-q" type="search" name="q" value="{{ $q }}" maxlength="100" placeholder="Seiten, Termine, Downloads" @if ($q === '') autofocus @endif
                    class="h-12 min-w-0 flex-1 bg-transparent text-base outline-none" />
                <button type="submit" class="h-9 rounded-full bg-dpsg-blau px-4 text-sm font-semibold text-white hover:bg-dpsg-rot">Suchen</button>
            </div>
        </form>

        @if ($zuKurz)
            <p class="mt-6 text-text-secondary">Bitte mindestens zwei Buchstaben eingeben.</p>
        @elseif ($q !== '')
            @if (count($anzahl) > 1)
                <nav aria-label="Bereich" class="mt-5 inline-flex max-w-full overflow-x-auto rounded-full bg-surface-muted p-1">
                    <a href="{{ route('suche', ['q' => $q]) }}" @class(['inline-flex h-11 items-center whitespace-nowrap rounded-full px-4 text-[15px] font-semibold', 'bg-white text-dpsg-blau shadow-sm' => $bereich === null, 'text-text-secondary' => $bereich !== null]) @if ($bereich === null) aria-current="page" @endif>Alle <span class="ml-1 text-xs opacity-70">{{ $gesamt }}</span></a>
                    @foreach ($anzahl as $schluessel => $n)
                        <a href="{{ route('suche', ['q' => $q, 'bereich' => $schluessel]) }}" @class(['inline-flex h-11 items-center whitespace-nowrap rounded-full px-4 text-[15px] font-semibold', 'bg-white text-dpsg-blau shadow-sm' => $bereich === $schluessel, 'text-text-secondary' => $bereich !== $schluessel]) @if ($bereich === $schluessel) aria-current="page" @endif>{{ \App\Support\Suche::BEREICH_NAMEN[$schluessel] }} <span class="ml-1 text-xs opacity-70">{{ $n }}</span></a>
                    @endforeach
                </nav>
            @endif

            <p class="mt-4 text-sm text-text-secondary" role="status">
                {{ $treffer->count() === 0 ? 'Keine Treffer' : ($treffer->count() === 1 ? '1 Treffer' : $treffer->count().' Treffer') }} für „{{ $q }}“{{ $gesamt >= \App\Support\Suche::MAX ? ' (die besten '.\App\Support\Suche::MAX.')' : '' }}.
            </p>

            @if ($treffer->isEmpty())
                <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
                    <p class="font-semibold">Nichts gefunden.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-[15px] text-text-secondary">
                        <li>Ein kürzeres oder anderes Wort probieren, z. B. „Lager“ statt „Sommerlager 2026“.</li>
                        <li>Im <a href="{{ route('calendar.index') }}" class="text-dpsg-rot underline">Kalender</a> nach Terminen schauen.</li>
                        <li>Uns über das <a href="{{ url('/kontakt') }}" class="text-dpsg-rot underline">Kontaktformular</a> fragen.</li>
                    </ul>
                </div>
            @else
                <ol class="mt-3 space-y-3">
                    @foreach ($treffer as $t)
                        <li>
                            @php($inhalt = '<p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">'.e($t['art']).($t['datum'] ? ' · '.e($t['datum']) : '').'</p>'
                                .'<p class="mt-1 text-lg font-bold text-dpsg-blau">'.e($t['titel']).'</p>'
                                .($t['auszug'] !== '' ? '<p class="mt-1 text-[15px] leading-relaxed text-text [&_mark]:rounded [&_mark]:bg-dpsg-yellow [&_mark]:px-0.5 [&_mark]:text-text">'.$t['auszug'].'</p>' : ''))
                            @if ($t['url'])
                                <a href="{{ $t['url'] }}" class="block min-h-11 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 transition hover:ring-dpsg-blau focus-visible:outline-3 focus-visible:outline-dpsg-blau">{!! $inhalt !!}</a>
                            @else
                                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">{!! $inhalt !!}</div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        @endif
    </div>
@endsection
