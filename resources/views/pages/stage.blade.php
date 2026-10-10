@extends('layouts.app')

{{-- Stufenseite: Kopfband in Stufenfarbe mit Maskottchen, Text mit
     Häkchen-Aufzählung, daneben Mitmachen / Ansprechpersonen / Termine,
     unten der Weg durch die Stufen. Erwartet: $page, $stage, $stagePages,
     $previous, $next, $dak, $staemmeCount, $events --}}

@section('seo_title', $page->seo_title ?: $page->title.' ('.$stage['age'].') – '.config('dpsg.site.name'))
@section('seo_description', $page->seo_description ?: $stage['label'].' ('.$stage['age'].'): '.$stage['tagline'].'. Die Stufe in der DPSG im '.config('dpsg.site.region').'.')

@php($overview = \App\Models\Page::query()->published()->where('slug', config('dpsg.stages_page'))->first())

@section('hero')
    <section class="stufe-{{ $page->stage }} relative overflow-hidden bg-(--stufe) text-(--stufe-ink)">
        @if ($page->coverMedia)
            {{-- Titelbild rechts, links in die Stufenfarbe auslaufend --}}
            <x-picture :media="$page->coverMedia" loading="eager" fetchpriority="high" class="absolute inset-y-0 right-0 hidden h-full w-3/5 object-cover md:block" />
            <div class="absolute inset-0 hidden bg-linear-to-r from-(--stufe) from-40% to-transparent md:block" aria-hidden="true"></div>
        @else
            <div class="stufe-pattern absolute inset-0" aria-hidden="true"></div>
        @endif

        <div class="relative mx-auto grid max-w-6xl items-center gap-8 px-4 pt-20 pb-16 md:grid-cols-[1fr_auto] md:pt-24 md:pb-24">
            <div>
                @if ($overview)
                    <nav aria-label="Brotkrumen" class="mb-4 text-sm font-bold opacity-85">
                        <a href="{{ url('/'.$overview->slug) }}" class="underline-offset-4 hover:underline">{{ $overview->title }}</a>
                        <span aria-hidden="true"> › </span>
                        <span aria-current="page">{{ $page->title }}</span>
                    </nav>
                @endif
                <p class="inline-block rounded-full bg-(--stufe-ink) px-3 py-1 text-sm font-bold text-(--stufe)">{{ $stage['age'] }}</p>
                <h1 class="mt-3 font-serif text-4xl font-bold md:text-6xl">{{ $page->title }}</h1>
                <p class="mt-3 max-w-xl text-xl md:text-2xl">{{ $stage['tagline'] }}</p>
            </div>
            <div class="order-first justify-self-end md:order-none md:justify-self-auto" aria-hidden="true">
                <div class="flex h-32 w-32 items-center justify-center rounded-full bg-white p-6 shadow-xl ring-8 ring-white/25 md:h-56 md:w-56 md:p-10">
                    <x-static-image :src="$stage['mascot']" class="max-h-full max-w-full object-contain" />
                </div>
            </div>
        </div>
        @if ($page->coverMedia?->credit)
            <span class="absolute bottom-9 right-2 bg-black/50 px-1 text-[10px] text-white">© {{ $page->coverMedia->credit }}</span>
        @endif
    </section>
@endsection

@section('content')
    <div class="stufe-{{ $page->stage }} mx-auto grid max-w-6xl gap-10 px-4 py-12 lg:grid-cols-3">
        <article class="prose prose-stufe prose-lg max-w-none lg:col-span-2">
            {!! str($page->body)->sanitizeHtml()->glossar()->embeds() !!}
        </article>

        <aside class="space-y-6" aria-label="Mitmachen und Kontakt">
            <div class="rounded-lg border-t-4 border-(--stufe) bg-white p-5 shadow-md">
                <h2 class="font-serif text-lg font-bold">Mitmachen</h2>
                <p class="mt-2 text-sm text-text-secondary">
                    @if (! Route::has('gruppierungen.index'))
                        Komm einfach mal zu einer Gruppenstunde vorbei. Sag uns vorher kurz Bescheid, dann wissen die Leiter*innen, dass du kommst.
                    @elseif ($staemmeCount > 0)
                        In <strong class="text-text">{{ $staemmeCount }} {{ $staemmeCount === 1 ? 'Stamm' : 'Stämmen' }}</strong> im {{ config('dpsg.site.region') }} gibt es eine Gruppe dieser Stufe.
                    @else
                        Frag im Stamm in deiner Nähe nach, ob es dort eine Gruppe dieser Stufe gibt.
                    @endif
                </p>
                @if (Route::has('gruppierungen.index'))
                    <a href="{{ route('gruppierungen.index', ['stufe' => $page->stage]) }}" class="mt-4 inline-flex items-center gap-2 bg-(--stufe) px-4 py-2 font-bold text-(--stufe-ink) hover:opacity-90">
                        <x-heroicon-o-map-pin class="h-5 w-5" aria-hidden="true" />
                        Stamm in der Nähe finden
                    </a>
                @else
                    <a href="{{ url('/kontakt') }}" class="mt-4 inline-flex items-center gap-2 bg-(--stufe) px-4 py-2 font-bold text-(--stufe-ink) hover:opacity-90">
                        <x-heroicon-o-envelope class="h-5 w-5" aria-hidden="true" />
                        Schnupperstunde anfragen
                    </a>
                @endif
            </div>

            <a href="#dak" class="flex items-center gap-4 rounded-lg bg-white p-5 shadow-md hover:shadow-lg">
                @if ($dak->isNotEmpty())
                    <span class="flex -space-x-3" aria-hidden="true">
                        @foreach ($dak->take(4) as $person)
                            <span class="rounded-full ring-2 ring-white">@include('partials.person-avatar', ['person' => $person, 'size' => 'h-11 w-11 text-sm'])</span>
                        @endforeach
                    </span>
                @else
                    <x-heroicon-o-user-group class="h-10 w-10 shrink-0 text-(--stufe)" aria-hidden="true" />
                @endif
                <span>
                    <span class="block font-serif text-lg font-bold leading-tight">{{ config('dpsg.site.ak') }}</span>
                    <span class="text-sm text-text-secondary">{{ $dak->isNotEmpty() ? 'Wer die Stufe im '.config('dpsg.site.level').' gestaltet' : 'Mitmachen im '.\App\Support\Site::akShort() }}</span>
                </span>
            </a>

            <div class="rounded-lg bg-white p-5 shadow-md">
                <h2 class="font-serif text-lg font-bold">Termine</h2>
                @if ($events->isNotEmpty())
                    <ul class="mt-3 divide-y divide-surface-muted">
                        @foreach ($events as $event)
                            <li class="py-2 first:pt-0">
                                <a href="{{ route('events.show', $event->slug) }}" class="group block text-sm">
                                    <span class="block text-xs font-bold uppercase tracking-wide text-text-secondary">{{ $event->starts_at->translatedFormat('j. F Y') }}</span>
                                    <span class="font-bold group-hover:text-dpsg-rot">{{ $event->title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-text-secondary">Gerade stehen keine Veranstaltungen speziell für diese Stufe an.</p>
                @endif
                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm font-bold">
                    <a href="{{ route('events.index', ['stufe' => $page->stage]) }}" class="text-dpsg-rot underline">Alle Veranstaltungen</a>
                    <a href="{{ route('calendar.index', ['stufe' => $page->stage]) }}" class="text-dpsg-rot underline">Kalender</a>
                </div>
            </div>
        </aside>
    </div>

    {{-- Arbeitskreis der Stufe (im DV: Diözesanarbeitskreis, DAK) --}}
    <section id="dak" class="stufe-{{ $page->stage }} mt-6 scroll-mt-24 border-y-4 border-(--stufe) bg-white py-12" aria-labelledby="dak-heading">
        <div class="mx-auto max-w-6xl px-4">
            <p class="text-sm font-bold uppercase tracking-wider text-text-secondary">{{ \App\Support\Site::akShort() }} {{ $stage['label'] }}</p>
            <h2 id="dak-heading" class="mt-1 font-serif text-3xl font-bold">{{ \App\Support\Site::akNominativ() }}</h2>
            <p class="mt-3 max-w-3xl text-text-secondary">
                @if (filled(config('dpsg.site.ak_text')))
                    {{ config('dpsg.site.ak_text') }}
                @else
                    Im {{ config('dpsg.site.ak') }}@if (config('dpsg.site.ak_short')) ({{ config('dpsg.site.ak_short') }})@endif gestalten Ehrenamtliche gemeinsam mit den Stufenreferent*innen die Arbeit der Stufe im {{ config('dpsg.site.level') }}:
                    Sie planen Aktionen und Fortbildungen, unterstützen die Leiter*innen in den Stämmen und bringen die Stufe in die Gremien ein.
                @endif
            </p>
        </div>
        @if ($dak->isNotEmpty())
            <div class="mt-8">
                @include('partials.team', ['people' => $dak, 'teamPhoto' => null])
            </div>
        @endif
        <div class="mx-auto max-w-6xl px-4">
            <p class="mt-2 flex flex-wrap items-center gap-3 rounded-lg bg-surface-muted p-4 text-sm">
                <x-heroicon-o-hand-raised class="h-5 w-5 shrink-0 text-(--stufe)" aria-hidden="true" />
                <span>Lust, im {{ \App\Support\Site::akShort() }} {{ $stage['label'] }} mitzuarbeiten? @include('partials.mitmachen-ansprechpersonen', ['mail' => $dak->filter(fn ($p) => $p->publicEmail()), 'members' => $dak->filter(fn ($p) => $p->gremium === 'dak')])@if ($contactUrl = \App\Models\ContactTopic::urlFor('stage', $page->stage)) – oder schreib uns direkt über das <a href="{{ $contactUrl }}" class="font-bold text-dpsg-rot underline">Kontaktformular</a>@endif.</span>
            </p>
        </div>
    </section>

    {{-- Weg durch die Stufen --}}
    <nav aria-label="Alle Stufen" class="mx-auto mt-14 max-w-6xl px-4">
        <h2 class="mb-6 font-serif text-2xl font-bold">Der Weg durch die Stufen</h2>
        <div class="relative">
        <span class="absolute inset-x-[10%] top-8 h-1 rounded bg-surface-muted md:top-11" aria-hidden="true"></span>
        <ol class="relative grid grid-cols-5 gap-2">
            @foreach ($stagePages as $stagePage)
                @php($s = config('dpsg.stages.'.$stagePage->stage))
                @php($current = $stagePage->is($page))
                <li class="stufe-{{ $stagePage->stage }} relative text-center">
                    <a href="{{ url('/'.$stagePage->slug) }}" class="group flex flex-col items-center" @if ($current) aria-current="page" @endif>
                        <span @class([
                            'flex items-center justify-center rounded-full border-4 border-(--stufe) bg-white p-2 transition',
                            'h-16 w-16 md:h-22 md:w-22 shadow-lg ring-4 ring-(--stufe)/30' => $current,
                            'h-16 w-16 md:h-22 md:w-22 opacity-80 grayscale-[35%] group-hover:opacity-100 group-hover:grayscale-0' => ! $current,
                        ])>
                            <x-static-image :src="$s['mascot']" class="max-h-full max-w-full object-contain" loading="lazy" />
                        </span>
                        <span @class(['mt-2 text-xs font-bold leading-tight sm:block sm:text-sm', 'text-dpsg-rot' => $current, 'max-sm:sr-only group-hover:text-dpsg-rot' => ! $current])>{{ $s['label'] }}</span>
                        <span class="hidden text-xs text-text-secondary sm:block">{{ $s['age'] }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
        </div>

        <div class="mt-10 flex flex-wrap justify-between gap-4">
            @if ($previous)
                <a href="{{ url('/'.$previous->slug) }}" class="stufe-{{ $previous->stage }} group inline-flex items-center gap-2 border-l-4 border-(--stufe) bg-white px-4 py-3 shadow-md hover:shadow-lg">
                    <x-heroicon-o-arrow-left class="h-5 w-5 text-text-secondary" aria-hidden="true" />
                    <span><span class="block text-xs text-text-secondary">Jüngere Stufe</span><span class="font-bold group-hover:text-dpsg-rot">{{ $previous->title }}</span></span>
                </a>
            @else
                <span></span>
            @endif
            @if ($next)
                <a href="{{ url('/'.$next->slug) }}" class="stufe-{{ $next->stage }} group inline-flex items-center gap-2 border-r-4 border-(--stufe) bg-white px-4 py-3 text-right shadow-md hover:shadow-lg">
                    <span><span class="block text-xs text-text-secondary">Nächste Stufe</span><span class="font-bold group-hover:text-dpsg-rot">{{ $next->title }}</span></span>
                    <x-heroicon-o-arrow-right class="h-5 w-5 text-text-secondary" aria-hidden="true" />
                </a>
            @endif
        </div>
    </nav>
@endsection
