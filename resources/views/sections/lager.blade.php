@extends('layouts.app')

{{-- Startseite eines Diözesanlagers (Rubrik /dioezesanlager) im Aussehen
     des Lagers (config dpsg.sections.<lager>.theme). Kopf und Fuß bleiben DPSG. --}}
@section('seo_title', ($landing?->seo_title ?: $config['label'].' – '.($config['titel'] ?? 'Diözesanlager')).' – '.config('dpsg.site.name'))
@if ($landing?->seo_description)
    @section('seo_description', $landing->seo_description)
@endif
@if ($canonical)
    @section('canonical', $canonical)
@endif
@section('main_class', \App\Support\Lager::klasse($section))

@php($lagerKlasse = \App\Support\Lager::klasse($section))
@php($fakten = array_filter(['Wann' => $config['zeitraum'] ?? null, 'Wo' => $config['ort'] ?? null, 'Motto' => $config['motto'] ?? null]))

@section('hero')
    <section class="{{ $lagerKlasse }} lager-wolken relative overflow-hidden pt-24 pb-12 md:pt-40 md:pb-16">
        <div class="relative mx-auto grid w-full max-w-6xl items-center gap-10 px-4 md:grid-cols-5">
            <div class="md:col-span-3">
                <p class="flex flex-wrap gap-2">
                    <span class="lager-pill lager-pill-akzent">{{ $config['kind'] ?? 'Diözesanlager' }} {{ $config['jahr'] ?? '' }}</span>
                    @if ($config['archiv'] ?? false)
                        <span class="lager-pill lager-pill-hell">Archiv</span>
                    @endif
                </p>
                <h1 class="mt-5 text-[clamp(3rem,8vw,5rem)] font-extrabold">{{ $landing?->title ?? $config['label'] }}</h1>
                @if ($config['titel'] ?? null)
                    <p class="mt-3 text-2xl font-semibold text-(--lager-kopf)">{{ $config['titel'] }}</p>
                @endif
                @if ($fakten)
                    <dl class="mt-8 grid gap-3 sm:grid-cols-3">
                        @foreach ($fakten as $label => $wert)
                            <div class="lager-karte px-5 py-4">
                                <dt class="text-xs font-semibold uppercase tracking-wider text-(--lager-akzent-text)">{{ $label }}</dt>
                                <dd class="mt-1 font-semibold leading-snug text-(--lager-kopf)">{{ $wert }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
            @if ($config['logo'] ?? null)
                {{-- Hochformatige Logos (Scoutergy, Schongse) frei, breite Schriftzüge (futurscout, Scoutgames) auf einer Karte --}}
                @php($breit = ($config['logo_size'][0] ?? 1) > 1.8 * ($config['logo_size'][1] ?? 1))
                <picture @class(['mx-auto block md:col-span-2', 'w-36 md:w-64' => ! $breit, 'lager-karte w-full max-w-md p-4' => $breit])>
                    <source type="image/webp" srcset="{{ asset($config['logo'].'.webp') }}" />
                    <img src="{{ asset($config['logo'].'.png') }}" alt="{{ $config['logo_alt'] ?? '' }}" width="{{ $config['logo_size'][0] ?? 480 }}" height="{{ $config['logo_size'][1] ?? 665 }}" loading="eager" @class(['h-auto w-full', 'drop-shadow-xl' => ! $breit]) />
                </picture>
            @endif
        </div>
    </section>
@endsection

@section('content')
    @include('sections.nav', ['section' => $section, 'current' => $config['landing']])

    <div class="mx-auto max-w-6xl px-4">
        @if ($config['video'] ?? null)
            <figure class="lager-karte mt-12 overflow-hidden p-2 md:p-3">
                <video controls playsinline preload="none" class="aspect-video w-full rounded-[22px] bg-black"
                       @if ($config['video_poster'] ?? null) poster="{{ asset($config['video_poster']) }}" @endif>
                    <source src="{{ asset($config['video']) }}" type="video/mp4" />
                    <a href="{{ asset($config['video']) }}">Video herunterladen</a>
                </video>
                <figcaption class="px-3 py-2 text-sm text-(--lager-text)">Teaser zum {{ $config['label'] }} – {{ $config['titel'] ?? '' }}</figcaption>
            </figure>
        @endif

        @if ($landing && trim(strip_tags((string) $landing->body)) !== '')
            <section class="lager-karte mt-12 p-8 md:p-12">
                <div class="prose prose-lg max-w-none">{!! str($landing->body)->sanitizeHtml()->embeds()->galerie() !!}</div>
            </section>
        @endif

        @if ($pages->isNotEmpty())
            <section class="mt-16" aria-labelledby="lager-seiten">
                <h2 id="lager-seiten" class="text-3xl md:text-4xl">Rund ums Lager</h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pages as $lagerSeite)
                        <a href="{{ url('/'.$lagerSeite->slug) }}" class="lager-karte group flex flex-col p-7">
                            <h3 class="text-2xl">{{ $lagerSeite->title }}</h3>
                            <p class="mt-2 flex-1">{{ $lagerSeite->seo_description ?: \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish(html_entity_decode(strip_tags(str_replace('<', ' <', (string) $lagerSeite->body)))), 140) }}</p>
                            <span class="mt-5 self-start lager-pill lager-pill-akzent">Ansehen <x-heroicon-o-arrow-right class="h-4 w-4 transition group-hover:translate-x-1" aria-hidden="true" /></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($posts->isNotEmpty())
            <section class="mt-16" aria-labelledby="lager-beitraege">
                <h2 id="lager-beitraege" class="text-3xl md:text-4xl">Aus der Lagerzeit</h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($posts as $post)
                        <article class="lager-karte flex flex-col overflow-hidden">
                            @if ($post->coverMedia)
                                <x-picture :media="$post->coverMedia" alt="" sizes="(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw" class="aspect-[16/9] w-full object-cover" />
                            @endif
                            <div class="flex flex-1 flex-col p-6">
                                <p class="text-xs font-semibold uppercase tracking-wider text-(--lager-akzent-text)">{{ $post->published_at?->locale('de')->isoFormat('D. MMMM YYYY') }}</p>
                                <h3 class="mt-1 text-xl"><a href="{{ url('/aktuelles/'.$post->slug) }}" class="hover:underline">{{ $post->title }}</a></h3>
                                @if ($post->teaser)<p class="mt-2 flex-1">{{ $post->teaser }}</p>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <a href="{{ route('sections.posts', $section) }}" class="lager-button mt-8">Alle Beiträge</a>
            </section>
        @endif
    </div>

    @if ($laufband = $config['laufband'] ?? null)
        {{-- Laufband wie auf der alten Seite; Text doppelt für die Endlosschleife --}}
        <div class="lager-laufband mt-20" aria-hidden="true">
            <div>@for ($i = 0; $i < 4; $i++)@foreach ($laufband as $wort)<span class="px-4">{{ $wort }}</span><span>⚡️</span>@endforeach @endfor</div>
        </div>
    @endif
@endsection
