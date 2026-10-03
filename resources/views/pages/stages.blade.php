@extends('layouts.app')

{{-- Übersicht „Die Stufen“: Einleitung aus dem CMS, Altersleiste (welche
     Stufe für welches Alter, mit Überschneidungen) und je Stufe eine
     Kachel mit Maskottchen, Leitsatz und Textanfang der Stufenseite.
     Erwartet: $page, $stagePages, $staemmeCounts --}}

@section('seo_title', $page->seo_title ?: $page->title.' – DPSG Diözesanverband Trier')
@section('seo_description', $page->seo_description ?: 'Biber, Wölflinge, Jungpfadfinder*innen, Pfadfinder*innen und Rover: die fünf Altersstufen der DPSG im Bistum Trier.')

@php
    $stages = config('dpsg.stages');
    $minAge = min(array_column($stages, 'from'));
    $maxAge = max(array_column($stages, 'to'));
    $span = max(1, $maxAge - $minAge);
    $pct = fn (int|float $age) => round(($age - $minAge) / $span * 100, 3);
@endphp

@section('hero')
    <section class="relative overflow-hidden bg-dpsg-blau text-white">
        <div class="stufe-pattern absolute inset-0" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-6xl px-4 pt-20 pb-16 md:pt-24 md:pb-24">
            <p class="text-sm font-bold uppercase tracking-widest text-white/75">{{ count($stages) }} Stufen · {{ $minAge }} bis {{ $maxAge }} Jahre</p>
            <h1 class="mt-2 font-serif text-4xl font-bold md:text-6xl">{{ $page->title }}</h1>
            <div class="mt-6 flex flex-wrap gap-3" aria-hidden="true">
                @foreach ($stagePages as $stagePage)
                    <span class="stufe-{{ $stagePage->stage }} flex h-14 w-14 items-center justify-center rounded-full border-4 border-(--stufe) bg-white p-2 md:h-16 md:w-16">
                        <img src="{{ asset($stages[$stagePage->stage]['mascot']) }}" alt="" class="max-h-full max-w-full object-contain" />
                    </span>
                @endforeach
            </div>
        </div>
    </section>
@endsection

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12">
        <div class="prose prose-lg max-w-3xl">
            {!! str($page->body)->sanitizeHtml()->glossar()->embeds() !!}
        </div>

        {{-- Altersleiste --}}
        <section class="mt-12 rounded-lg bg-white p-5 shadow-md md:p-8" aria-labelledby="altersleiste">
            <h2 id="altersleiste" class="font-serif text-xl font-bold">Welche Stufe passt zu welchem Alter?</h2>
            <p class="mt-1 text-sm text-text-secondary">Die Übergänge sind fließend – wann jemand wechselt, entscheidet die Gruppe vor Ort gemeinsam.</p>

            <ul class="mt-6 space-y-2.5">
                @foreach ($stagePages as $stagePage)
                    @php($s = $stages[$stagePage->stage])
                    <li class="stufe-{{ $stagePage->stage }} grid grid-cols-[9.25rem_1fr] items-center gap-3 sm:grid-cols-[11rem_1fr]">
                        <a href="{{ url('/'.$stagePage->slug) }}" class="text-[13px] leading-tight font-bold whitespace-nowrap hover:text-dpsg-rot sm:text-sm">{{ $s['label'] }}<span class="block text-xs font-normal text-text-secondary sm:hidden">{{ $s['age'] }}</span></a>
                        <div class="relative h-9 rounded bg-surface-muted/60">
                            <a href="{{ url('/'.$stagePage->slug) }}"
                               class="absolute inset-y-0 flex items-center justify-center rounded bg-(--stufe) px-2 text-xs font-bold whitespace-nowrap text-(--stufe-ink) shadow-sm transition hover:brightness-110 sm:text-sm"
                               style="left: {{ $pct($s['from']) }}%; width: {{ $pct($s['to']) - $pct($s['from']) }}%;"
                               aria-label="{{ $s['label'] }}: {{ $s['age'] }}"><span class="hidden sm:inline">{{ $s['from'] }}–{{ $s['to'] }}</span></a>
                        </div>
                    </li>
                @endforeach
            </ul>
            {{-- Achse --}}
            <div class="mt-2 grid grid-cols-[9.25rem_1fr] gap-3 sm:grid-cols-[11rem_1fr]" aria-hidden="true">
                <span class="text-right text-xs text-text-secondary">Alter</span>
                <div class="relative h-5 border-t border-dpsg-mittelbeige">
                    @for ($age = $minAge; $age <= $maxAge; $age += 2)
                        <span class="absolute top-1 -translate-x-1/2 text-xs text-text-secondary" style="left: {{ $pct($age) }}%">{{ $age }}</span>
                    @endfor
                </div>
            </div>
        </section>

        {{-- Stufenkacheln --}}
        <div class="mt-14 space-y-8">
            @foreach ($stagePages as $stagePage)
                @php($s = $stages[$stagePage->stage])
                @php($excerpt = \Illuminate\Support\Str::limit(trim(strip_tags(preg_match('#<p>(.*?)</p>#s', (string) $stagePage->body, $m) ? $m[1] : '')), 260))
                @php($count = $staemmeCounts[$stagePage->stage] ?? 0)
                <article @class([
                    'stufe-'.$stagePage->stage, 'group relative grid overflow-hidden rounded-lg bg-white shadow-md transition hover:shadow-xl',
                    'md:grid-cols-[18rem_1fr]' => $loop->odd,
                    'md:grid-cols-[1fr_18rem]' => $loop->even,
                ])>
                    <div @class(['stufe-pattern relative flex items-center justify-center bg-(--stufe) p-8', 'md:order-last' => $loop->even])>
                        <div class="flex h-36 w-36 items-center justify-center rounded-full bg-white p-6 shadow-lg transition duration-300 group-hover:scale-105 md:h-44 md:w-44">
                            <img src="{{ asset($s['mascot']) }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy" />
                        </div>
                    </div>
                    <div class="flex flex-col justify-center p-6 md:p-8">
                        <p class="text-sm font-bold text-text-secondary">{{ $s['age'] }}</p>
                        <h2 class="mt-1 font-serif text-2xl font-bold md:text-3xl">
                            <a href="{{ url('/'.$stagePage->slug) }}" class="after:absolute after:inset-0 hover:text-dpsg-rot focus:outline-none">{{ $stagePage->title }}</a>
                        </h2>
                        <p class="mt-1 text-lg italic">{{ $s['tagline'] }}</p>
                        @if ($excerpt !== '')
                            <p class="mt-3 text-text-secondary">{{ $excerpt }}</p>
                        @endif
                        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm font-bold">
                            <span class="inline-flex items-center gap-2 text-dpsg-rot">Mehr erfahren <x-heroicon-o-arrow-right class="h-4 w-4 transition group-hover:translate-x-1" aria-hidden="true" /></span>
                            @if ($count > 0)
                                <span class="inline-flex items-center gap-1.5 font-normal text-text-secondary">
                                    <x-heroicon-o-map-pin class="h-4 w-4" aria-hidden="true" /> in {{ $count }} {{ $count === 1 ? 'Stamm' : 'Stämmen' }}
                                </span>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <section class="mt-14 flex flex-col items-start gap-4 rounded-lg bg-dpsg-hellbeige p-6 md:flex-row md:items-center md:justify-between md:p-8">
            <div>
                <h2 class="font-serif text-xl font-bold">Lust mitzumachen?</h2>
                <p class="mt-1 text-text-secondary">Im ganzen Bistum Trier gibt es Stämme mit Gruppen für jedes Alter.</p>
            </div>
            <a href="{{ route('gruppierungen.index') }}" class="inline-flex shrink-0 items-center gap-2 bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">
                <x-heroicon-o-map-pin class="h-5 w-5" aria-hidden="true" /> Stamm in der Nähe finden
            </a>
        </section>
    </div>
@endsection
