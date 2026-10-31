@extends('layouts.app')

{{-- Rubrik „Diözesanlager“: Einleitung aus dem CMS, je Lager eine Karte im
     Aussehen des Lagers. Erwartet: $page, $items (key, config, page) --}}

@section('seo_title', $page->seo_title ?: $page->title.' – '.config('dpsg.site.name'))
@section('seo_description', $page->seo_description ?: 'Die Diözesanlager des '.config('dpsg.site.name_genitive').': '.$items->map(fn ($i) => $i['config']['label'].' '.($i['config']['jahr'] ?? ''))->implode(', ').'.')

@section('hero')
    <section class="relative overflow-hidden bg-dpsg-blau text-white">
        <div class="stufe-pattern absolute inset-0" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-6xl px-4 pt-20 pb-16 md:pt-24 md:pb-24">
            <p class="text-sm font-bold uppercase tracking-widest text-white/75">{{ config('dpsg.site.name') }}</p>
            <h1 class="mt-2 font-serif text-4xl font-bold md:text-6xl">{{ $page->title }}</h1>
            @if ($page->seo_description)
                <p class="mt-4 max-w-2xl text-lg text-white/90">{{ $page->seo_description }}</p>
            @endif
        </div>
    </section>
@endsection

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12">
        @if (trim(strip_tags((string) $page->body)) !== '')
            <div class="prose prose-lg max-w-3xl">
                {!! str($page->body)->sanitizeHtml()->glossar()->embeds()->mailformular() !!}
            </div>
        @endif

        @if ($items->isEmpty())
            <p class="mt-12 text-text-secondary">Noch keine Lager eingetragen.</p>
        @else
            <div class="mt-12 grid gap-8 md:grid-cols-2">
                @foreach ($items as $item)
                    @php($c = $item['config'])
                    @php($lagerKlasse = \App\Support\Lager::klasse($item['key']))
                    <article @class([$lagerKlasse, 'group relative flex flex-col overflow-hidden', 'lager-karte' => $lagerKlasse !== '', 'rounded-lg bg-white shadow-md' => $lagerKlasse === ''])>
                        <div @class(['flex items-center gap-6 p-6', 'lager-wolken' => $lagerKlasse !== '', 'bg-dpsg-hellbeige' => $lagerKlasse === ''])>
                            @if ($c['logo'] ?? null)
                                <picture class="block w-24 shrink-0">
                                    <source type="image/webp" srcset="{{ asset($c['logo'].'.webp') }}" />
                                    <img src="{{ asset($c['logo'].'.png') }}" alt="" width="{{ $c['logo_size'][0] ?? 480 }}" height="{{ $c['logo_size'][1] ?? 665 }}" loading="lazy" class="h-auto w-full" />
                                </picture>
                            @endif
                            <div>
                                <p class="flex flex-wrap gap-2 text-sm">
                                    <span class="lager-pill lager-pill-akzent">{{ $c['jahr'] ?? '' }}</span>
                                    @if ($c['archiv'] ?? false)<span class="lager-pill lager-pill-hell">Archiv</span>@endif
                                </p>
                                <h2 class="mt-3 text-3xl font-bold">
                                    <a href="{{ url('/'.$c['landing']) }}" class="after:absolute after:inset-0 hover:underline">{{ $item['page']->title }}</a>
                                </h2>
                                @if ($c['titel'] ?? null)<p class="mt-1 font-semibold">{{ $c['titel'] }}</p>@endif
                            </div>
                        </div>
                        <dl class="grid flex-1 gap-x-6 gap-y-2 p-6 text-sm sm:grid-cols-[auto_1fr]">
                            @foreach (array_filter(['Wann' => $c['zeitraum'] ?? null, 'Wo' => $c['ort'] ?? null, 'Motto' => $c['motto'] ?? null]) as $label => $wert)
                                <dt class="font-semibold">{{ $label }}</dt>
                                <dd>{{ $wert }}</dd>
                            @endforeach
                        </dl>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
