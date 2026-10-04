@extends('layouts.app')

{{-- Übersicht „Fachbereiche“: Einleitung aus dem CMS, je Fachbereich eine
     Kachel in seiner CD-Farbe mit Kurzbeschreibung und Ansprechpersonen.
     Erwartet: $page, $items (key, config, page, contacts) --}}

@section('seo_title', $page->seo_title ?: $page->title.' – '.config('dpsg.site.name'))
@section('seo_description', $page->seo_description ?: 'Die Fachbereiche des '.config('dpsg.site.name_genitive').': '.$items->pluck('config.label')->implode(', ').'.')

@section('hero')
    <section class="relative overflow-hidden bg-dpsg-blau text-white">
        <div class="stufe-pattern absolute inset-0" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-6xl px-4 pt-20 pb-16 md:pt-24 md:pb-24">
            <p class="text-sm font-bold uppercase tracking-widest text-white/75">{{ $items->count() }} {{ $items->count() === 1 ? 'Fachbereich' : 'Fachbereiche' }}</p>
            <h1 class="mt-2 font-serif text-4xl font-bold md:text-6xl">{{ $page->title }}</h1>
            <div class="mt-6 flex flex-wrap gap-2" aria-hidden="true">
                @foreach ($items as $item)
                    <span class="fach-{{ $item['config']['color'] ?? 'internationales' }} rounded-full bg-(--fach) px-3 py-1 text-sm font-bold text-(--fach-ink)">{{ $item['config']['label'] }}</span>
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

        <div class="mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                @php($landing = $item['page'])
                @php($excerpt = \Illuminate\Support\Str::limit(trim(\Illuminate\Support\Str::before(strip_tags(preg_match('#<p>(.*?)</p>#s', (string) $landing->body, $m) ? $m[1] : ''), '[TODO')), 200))
                <article class="fach-{{ $item['config']['color'] ?? 'internationales' }} group relative flex flex-col overflow-hidden rounded-lg bg-white shadow-md transition hover:-translate-y-0.5 hover:shadow-xl">
                    <div class="stufe-pattern flex h-40 items-center justify-center bg-(--fach) p-6 [--stufe-ink:var(--fach-ink)]">
                        @if ($item['config']['logo'] ?? null)
                            @php($squareLogo = ($item['config']['logo_size'][0] ?? 744) <= ($item['config']['logo_size'][1] ?? 335) * 1.3)
                            <span @class(['flex items-center bg-white shadow-md', 'h-full rounded-lg px-4 py-2' => ! $squareLogo, 'h-28 w-28 justify-center rounded-full p-4' => $squareLogo])>
                                <picture>
                                    <source type="image/webp" srcset="{{ asset($item['config']['logo'].'.webp') }}" />
                                    <img src="{{ asset($item['config']['logo'].'.png') }}" alt="" width="{{ $item['config']['logo_size'][0] ?? 744 }}" height="{{ $item['config']['logo_size'][1] ?? 335 }}" class="h-auto max-h-24 w-auto" loading="lazy" />
                                </picture>
                            </span>
                        @else
                            <span class="flex h-24 w-24 items-center justify-center rounded-full bg-white shadow-md">
                                <x-dynamic-component :component="'heroicon-o-'.($item['config']['icon'] ?? 'sparkles')" class="h-12 w-12 text-text" aria-hidden="true" />
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-6">
                        <h2 class="font-serif text-2xl font-bold">
                            <a href="{{ url('/'.$landing->slug) }}" class="after:absolute after:inset-0 group-hover:text-dpsg-rot focus:outline-none">{{ $item['config']['label'] }}</a>
                        </h2>
                        @if ($landing->seo_description)
                            <p class="mt-1 italic">{{ $landing->seo_description }}</p>
                        @endif
                        @if ($excerpt !== '')
                            <p class="mt-3 flex-1 text-sm text-text-secondary">{{ $excerpt }}</p>
                        @endif
                        <div class="mt-5 flex items-center justify-between gap-3">
                            @if ($item['contacts']->isNotEmpty())
                                <span class="flex items-center gap-2 text-sm">
                                    <span class="flex -space-x-2" aria-hidden="true">
                                        @foreach ($item['contacts']->take(3) as $person)
                                            <span class="rounded-full ring-2 ring-white">@include('partials.person-avatar', ['person' => $person, 'size' => 'h-9 w-9 text-xs'])</span>
                                        @endforeach
                                    </span>
                                    <span class="text-text-secondary">{{ $item['contacts']->pluck('name')->implode(', ') }}</span>
                                </span>
                            @else
                                <span></span>
                            @endif
                            <x-heroicon-o-arrow-right class="h-5 w-5 shrink-0 text-dpsg-rot transition group-hover:translate-x-1" aria-hidden="true" />
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
@endsection
