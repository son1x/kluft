@extends('layouts.app')

@section('seo_title', 'Suche – '.config('dpsg.site.name'))

@push('head')
    <meta name="robots" content="noindex, follow" />
@endpush

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">Suche</h1>

        <form method="get" action="{{ route('suche') }}" role="search" class="mt-6 flex gap-2">
            <label for="suche-q" class="sr-only">Suchbegriff</label>
            <input id="suche-q" type="search" name="q" value="{{ $q }}" maxlength="100" placeholder="z. B. Pfingstlager, Juleica, Anmeldung" @if ($q === '') autofocus @endif
                class="min-w-0 flex-1 border border-dpsg-mittelbeige bg-white px-3 py-2" />
            <button type="submit" class="bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">Suchen</button>
        </form>

        @if ($zuKurz)
            <p class="mt-6 text-text-secondary">Bitte mindestens zwei Buchstaben eingeben.</p>
        @elseif ($q !== '')
            <p class="mt-6 text-sm text-text-secondary" role="status">
                {{ $treffer->count() === 0 ? 'Keine Treffer' : ($treffer->count() === 1 ? '1 Treffer' : $treffer->count().' Treffer') }} für „{{ $q }}“{{ $treffer->count() >= \App\Support\Suche::MAX ? ' (die besten '.\App\Support\Suche::MAX.')' : '' }}.
            </p>
            @if ($treffer->isEmpty())
                <p class="mt-2">Versuch es mit einem anderen oder kürzeren Wort, oder schau in den <a href="{{ url('/kontakt') }}" class="text-dpsg-rot underline">Kontakt</a>.</p>
            @endif
            <ol class="mt-4 space-y-5">
                @foreach ($treffer as $t)
                    <li class="border-b border-dpsg-mittelbeige pb-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-text-secondary">{{ $t['art'] }}@if ($t['datum']) · {{ $t['datum'] }}@endif</p>
                        <h2 class="mt-1 text-lg font-bold">
                            @if ($t['url'])
                                <a href="{{ $t['url'] }}" class="text-dpsg-blau hover:text-dpsg-rot hover:underline">{{ $t['titel'] }}</a>
                            @else
                                {{ $t['titel'] }}
                            @endif
                        </h2>
                        @if ($t['auszug'] !== '')
                            <p class="mt-1 text-sm [&_mark]:bg-dpsg-yellow [&_mark]:text-text">{!! $t['auszug'] !!}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
@endsection
