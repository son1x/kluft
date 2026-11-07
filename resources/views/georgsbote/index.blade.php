@extends('layouts.app')

@section('seo_title', 'Georgsbote – '.config('dpsg.site.name'))
@section('seo_description', 'Der Georgsbote, die Zeitschrift des '.config('dpsg.site.name_genitive').', und der Georgsbrief: alle Ausgaben als PDF, mit Inhaltsangabe und Suche.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">Georgsbote</h1>

        @if ($intro)
            <div class="prose mt-4 max-w-3xl">
                {!! str($intro->body)->before('[TODO')->sanitizeHtml() !!}
            </div>
        @endif

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <section class="lg:col-span-2" aria-labelledby="archiv-heading">
                @include('partials.section-heading', ['id' => 'archiv-heading', 'title' => 'Archiv'])

                @php($brief = $art === \App\Models\GeorgsboteIssue::BRIEF)
                <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <nav aria-label="Archiv wählen" class="flex gap-1 border-b border-gray-200">
                        @foreach ([\App\Models\GeorgsboteIssue::BOTE => 'Georgsbote', \App\Models\GeorgsboteIssue::BRIEF => 'Georgsbrief'] as $key => $name)
                            <a href="{{ route('georgsbote.index', array_filter(['art' => $key === 'brief' ? 'brief' : null, 'q' => $suche ?: null])) }}"
                               @if ($art === $key) aria-current="page" @endif
                               @class(['-mb-px border-b-4 px-4 py-2 font-bold', 'border-dpsg-rot text-dpsg-blau' => $art === $key, 'border-transparent text-text-secondary hover:text-dpsg-rot' => $art !== $key])>
                                {{ $name }} <span class="text-sm font-normal">({{ $anzahl[$key] ?? 0 }})</span>
                            </a>
                        @endforeach
                    </nav>
                    <form method="get" action="{{ route('georgsbote.index') }}" role="search" class="flex gap-2">
                        @if ($brief)<input type="hidden" name="art" value="brief" />@endif
                        <label for="archiv-suche" class="sr-only">Im Archiv suchen</label>
                        <input id="archiv-suche" type="search" name="q" value="{{ $suche }}" maxlength="100" placeholder="Thema suchen, z. B. Europa" class="w-56 rounded border-gray-300 text-sm" />
                        <button type="submit" class="bg-dpsg-blau px-3 py-2 text-sm font-bold text-white hover:bg-dpsg-rot">Suchen</button>
                    </form>
                </div>

                @if ($suche !== '')
                    <p class="mb-6 text-sm text-text-secondary">
                        Treffer für „{{ $suche }}“ in Titel und Inhalt. <a href="{{ route('georgsbote.index', array_filter(['art' => $brief ? 'brief' : null])) }}" class="text-dpsg-rot underline">Suche zurücksetzen</a>
                    </p>
                @endif

                @forelse ($jahrgaenge as $jahr => $ausgaben)
                    <h3 class="mb-4 mt-2 font-serif text-xl font-bold">{{ $brief ? $jahr : 'Jahrgang '.$jahr }}</h3>
                    <ul @class(['mb-10 grid gap-5', 'sm:grid-cols-2' => ! $brief])>
                        @foreach ($ausgaben as $ausgabe)
                            <li>
                                <a href="{{ $ausgabe->downloadUrl() }}" class="group flex h-full gap-4 bg-white p-3 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg" target="_blank" rel="noopener">
                                    @unless ($brief)
                                        <div class="flex aspect-[3/4] w-24 shrink-0 items-center justify-center self-start overflow-hidden bg-dpsg-hellbeige">
                                            @if ($cover = $ausgabe->coverUrl())
                                                <img src="{{ $cover }}" alt="" width="96" height="128" class="h-full w-full object-cover object-top" loading="lazy" decoding="async" />
                                            @else
                                                <span class="px-2 text-center font-serif text-sm font-bold text-dpsg-blau">Georgsbote<br />{{ $ausgabe->issue_no }}/{{ $ausgabe->year }}</span>
                                            @endif
                                        </div>
                                    @endunless
                                    <div class="min-w-0">
                                        @if ($brief)
                                            <span class="block font-bold group-hover:text-dpsg-rot">{{ $ausgabe->label() }}</span>
                                        @else
                                            <span class="block text-xs text-text-secondary">{{ $ausgabe->label() }}</span>
                                            <span class="block font-bold group-hover:text-dpsg-rot">{{ $ausgabe->title }}</span>
                                        @endif
                                        @if ($ausgabe->inhalt)
                                            <span class="mt-1 block text-sm">{{ $ausgabe->inhalt }}</span>
                                        @endif
                                        <span class="mt-1 block text-sm text-dpsg-rot">PDF öffnen</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @empty
                    <p class="text-text-secondary">
                        @if ($suche !== '')
                            Keine Treffer in diesem Archiv.
                        @else
                            Die Ausgaben werden gerade ins Archiv übernommen.
                        @endif
                    </p>
                @endforelse
            </section>

            {{-- Hinweis auf den Newsletter (beim DV „Georgsbrief“) --}}
            @if (\App\Support\Features::newsletter())
                <aside aria-label="{{ config('dpsg.newsletter.name') }}" class="self-start overflow-hidden bg-white shadow-md">
                    <a href="{{ route('newsletter.index') }}" class="block bg-[#eff2f7] p-4">
                        @include('newsletter.logo', ['class' => 'h-auto w-full'])
                    </a>
                    <div class="p-5">
                        <p class="text-sm">Lieber regelmäßig kurz informiert? Der <strong>{{ config('dpsg.newsletter.name') }}</strong> ist unser Newsletter mit Terminen und Neuigkeiten aus dem {{ config('dpsg.site.level') }} – direkt ins Postfach.</p>
                        <a href="{{ route('newsletter.index') }}" class="mt-4 inline-block bg-dpsg-blau px-4 py-2 text-sm font-bold text-white hover:bg-dpsg-rot">Zum {{ config('dpsg.newsletter.name') }}</a>
                    </div>
                </aside>
            @endif
        </div>
    </div>
@endsection
