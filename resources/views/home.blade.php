@extends('layouts.app')

@section('seo_title', $page->seo_title ?: config('dpsg.site.name'))
@if ($page->seo_description)
    @section('seo_description', $page->seo_description)
@endif

@section('hero')
    <section class="relative flex h-72 items-center overflow-hidden bg-dpsg-blau md:h-[30rem]">
        {{-- Titelbild der Seite „start“ oder der Instanz; mit der Funktion
             „Titelbild wechselt zufällig“ bei jedem Aufruf ein anderes. --}}
        @php($titelbild = \App\Support\Titelbild::fuer($page))
        @if ($titelbild)
            @if ($titelbild['media'])
                <x-picture :media="$titelbild['media']" loading="eager" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover" />
            @else
                <x-static-image :src="$titelbild['src']" :alt="$titelbild['alt']" fetchpriority="high" decoding="async" class="absolute inset-0 h-full w-full object-cover" style="object-position: {{ $titelbild['position'] }}" />
            @endif
            <div class="absolute inset-0 bg-gradient-to-r from-black/55 via-black/25 to-transparent" aria-hidden="true"></div>
            @if ($titelbild['credit'])
                @if ($titelbild['link'])
                    <a href="{{ $titelbild['link'] }}" target="_blank" rel="noopener" class="absolute bottom-1 right-2 max-w-[55%] bg-black/50 px-1 text-right text-[10px] leading-tight text-white hover:underline md:bottom-9 md:max-w-none">© {{ $titelbild['credit'] }}</a>
                @else
                    <span class="absolute bottom-1 right-2 max-w-[55%] bg-black/50 px-1 text-right text-[10px] leading-tight text-white md:bottom-9 md:max-w-none">© {{ $titelbild['credit'] }}</span>
                @endif
            @endif
        @else
            {{-- Ohne Titelbild (in der Seite "start" wählbar): DPSG-Blau mit
                 den Stufen-Maskottchen statt eines leeren Fotofelds. --}}
            <div class="absolute inset-0 bg-gradient-to-br from-dpsg-blau via-dpsg-blau to-[#001a30]" aria-hidden="true"></div>
            <div class="absolute right-8 top-1/2 hidden w-[27rem] -translate-y-1/2 flex-wrap justify-center gap-5 xl:flex xl:right-[max(2rem,calc((100vw-72rem)/2))]" aria-hidden="true">
                @foreach (config('dpsg.stages') as $key => $stage)
                    <div @class([
                        'flex h-32 w-32 items-center justify-center rounded-full border-4 bg-white p-5',
                        'border-stufe-biber' => $key === 'biber',
                        'border-stufe-woelflinge' => $key === 'woelflinge',
                        'border-stufe-jupfi' => $key === 'jupfi',
                        'border-stufe-pfadi' => $key === 'pfadi',
                        'border-stufe-rover' => $key === 'rover',
                    ])>
                        <x-static-image :src="$stage['mascot']" class="max-h-full max-w-full object-contain" loading="lazy" decoding="async" />
                    </div>
                @endforeach
            </div>
        @endif
        <div class="relative mx-auto w-full max-w-6xl px-4 pt-10 md:pl-32">
            <h1 class="max-w-2xl font-serif text-4xl font-bold text-white drop-shadow md:text-6xl">{{ $page->title }}</h1>
            @if ($page->seo_description)
                <p class="mt-3 max-w-xl text-lg text-white drop-shadow md:text-2xl">{{ $page->seo_description }}</p>
            @endif
            <span class="mt-5 block h-1.5 w-40 rounded-full bg-dpsg-rot" aria-hidden="true"></span>
        </div>
    </section>
@endsection

@section('content')
    @php($contact = config('dpsg.contact'))

    {{-- Einstieg: Text links, Kontaktbox rechts (Raster wie der Teaser-
         Block unter der Navigation auf bistum-trier.de). --}}
    <section class="mx-auto mt-12 max-w-6xl px-4">
        <div class="grid bg-white shadow-lg lg:grid-cols-3">
            <div class="prose max-w-none p-8 lg:col-span-2 prose-headings:font-serif prose-a:text-dpsg-rot">
                {!! str($page->body)->sanitizeHtml()->embeds()->mailformular() !!}
            </div>
            <aside class="flex flex-col justify-between gap-6 bg-dpsg-hellbeige p-8">
                <div>
                    <h2 class="mb-3 font-serif text-xl font-bold">{{ $contact['office'] }}</h2>
                    <p>{{ $contact['name'] }}@if (filled($contact['street'] ?? null))<br />{{ $contact['street'] }}@endif<br />{{ $contact['city'] }}</p>
                    <p class="mt-3">
                        @if (filled($contact['phone'] ?? null))
                            <a href="tel:{{ $contact['phone_href'] }}" class="hover:underline">{{ $contact['phone'] }}</a><br />
                        @endif
                        <a href="{{ \App\Support\Mailformular::href($contact['email']) }}" class="hover:underline">{{ $contact['email'] }}</a>
                    </p>
                </div>
                {{-- Visitenkarte (Ausbaustufe 1): zum Kontaktformular statt zur Seite des Vorstands. --}}
                <a href="{{ \App\Support\Site::officeUrl() }}" class="self-start border border-dpsg-rot px-4 py-2 text-dpsg-rot hover:bg-dpsg-rot hover:text-white">{{ \App\Support\Ausbau::gruppen() ? \App\Support\Site::office('zu', capitalize: true) : 'Kontakt aufnehmen' }}</a>
            </aside>
        </div>
    </section>

    @if (\App\Support\Ausbau::termine())
    <section class="mx-auto mt-20 max-w-6xl px-4" aria-labelledby="aktuelles-heading">
        @include('partials.section-heading', ['id' => 'aktuelles-heading', 'title' => 'Aktuelles'])

        @if ($posts->isEmpty())
            <p class="text-text-secondary">Noch keine Meldungen veröffentlicht.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <article class="flex flex-col bg-white shadow-md">
                        <a href="{{ url('/aktuelles/'.$post->slug) }}" class="block aspect-[16/9] overflow-hidden" tabindex="-1" aria-hidden="true">
                            @if ($post->coverMedia)
                                <x-picture :media="$post->coverMedia" alt="" sizes="(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw" class="h-full w-full object-cover" />
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-dpsg-hellbeige">
                                    <x-static-image :src="config('dpsg.site.logo')" class="h-20 w-auto opacity-80" loading="lazy" />
                                </div>
                            @endif
                        </a>
                        <div class="flex flex-1 flex-col p-5">
                            @if ($post->stage)
                                <div class="mb-2">@include('partials.stufe-badge', ['stage' => $post->stage])</div>
                            @endif
                            <h3 class="font-serif text-lg font-bold leading-snug text-dpsg-rot">
                                <a href="{{ url('/aktuelles/'.$post->slug) }}" class="hover:underline">{{ $post->title }}</a>
                            </h3>
                            <p class="mt-1 text-xs text-text-secondary">{{ $post->published_at?->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}</p>
                            @if ($post->event?->acceptsRegistrations())
                                <p class="mt-2"><span class="inline-flex items-center gap-1 rounded-full bg-dpsg-blau px-2 py-0.5 text-xs font-bold text-white"><x-heroicon-o-ticket class="h-3.5 w-3.5" aria-hidden="true" />Anmeldung offen</span></p>
                            @endif
                            <p class="mt-3 flex-1">{{ $post->teaser }}</p>
                            <a href="{{ url('/aktuelles/'.$post->slug) }}" class="mt-5 self-start border border-dpsg-rot px-3 py-1 text-sm text-dpsg-rot hover:bg-dpsg-rot hover:text-white">
                                Mehr<span class="sr-only"> zu „{{ $post->title }}“</span>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <a href="{{ url('/aktuelles') }}" class="mt-8 inline-block border border-dpsg-rot px-4 py-2 text-dpsg-rot hover:bg-dpsg-rot hover:text-white">Alle Meldungen</a>
    </section>
    @endif

    @if ($stagePages->isNotEmpty())
        <section class="mx-auto mt-20 max-w-6xl px-4" aria-labelledby="stufen-heading">
            @include('partials.section-heading', ['id' => 'stufen-heading', 'title' => 'Unsere Stufen'])

            <div class="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-5">
                @foreach ($stagePages as $stagePage)
                    @php($stage = config('dpsg.stages.'.$stagePage->stage))
                    <a href="{{ url('/'.$stagePage->slug) }}" class="group flex flex-col bg-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                        <div @class([
                            'flex aspect-square items-center justify-center overflow-hidden',
                            'bg-stufe-biber' => $stagePage->stage === 'biber',
                            'bg-stufe-woelflinge' => $stagePage->stage === 'woelflinge',
                            'bg-stufe-jupfi' => $stagePage->stage === 'jupfi',
                            'bg-stufe-pfadi' => $stagePage->stage === 'pfadi',
                            'bg-stufe-rover' => $stagePage->stage === 'rover',
                        ])>
                            <div class="flex aspect-square w-4/5 items-center justify-center rounded-full bg-white/90 p-[12%]">
                                <x-static-image :src="$stage['mascot']" class="max-h-full max-w-full object-contain" loading="lazy" />
                            </div>
                        </div>
                        <div class="bg-surface-muted px-4 py-3">
                            <span class="block font-bold group-hover:text-dpsg-rot">{{ $stage['label'] }}</span>
                            <span class="block text-sm text-text-secondary">{{ $stage['age'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Gruppenstunden (Stammes-Websites, Verwaltung → Gruppenstunden) --}}
    @php($gruppenstunden = \App\Models\Gruppenstunde::angezeigt())
    @if ($gruppenstunden->isNotEmpty())
        @include('partials.gruppenstunden', ['gruppen' => $gruppenstunden])
    @endif

    <section class="mt-20 bg-dpsg-blau text-white">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <h2 class="inline-block bg-dpsg-rot px-3 py-1 font-serif text-2xl font-bold">Mitmachen</h2>
            <p class="mt-5 max-w-3xl text-lg md:text-xl">
                {{ config('dpsg.site.join_text') }}
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                @if (Route::has('gruppierungen.index'))
                    <a href="{{ route('gruppierungen.index') }}" class="inline-block bg-white px-5 py-2 font-bold text-dpsg-blau hover:bg-dpsg-hellbeige">Stamm in der Nähe finden</a>
                    <a href="{{ url('/kontakt') }}" class="inline-block border border-white px-5 py-2 font-bold text-white hover:bg-white hover:text-dpsg-blau">Kontakt aufnehmen</a>
                @else
                    {{-- Stammes-Website: direkt zum Schnuppern anfragen. --}}
                    <a href="{{ url('/kontakt') }}" class="inline-block bg-white px-5 py-2 font-bold text-dpsg-blau hover:bg-dpsg-hellbeige">Schnupperstunde anfragen</a>
                    @if ($leiterUrl = \App\Support\Leitergewinnung::url())
                        <a href="{{ $leiterUrl }}" class="inline-block border border-white px-5 py-2 font-bold text-white hover:bg-white hover:text-dpsg-blau">{{ \App\Support\Leitergewinnung::TITEL }}</a>
                    @endif
                @endif
            </div>
        </div>
    </section>

    @if ($highlightPages->isNotEmpty())
        <section class="bg-surface-muted">
            <div @class(['mx-auto grid max-w-6xl gap-6 px-4 py-16 md:grid-cols-2', 'lg:grid-cols-3' => $highlightPages->count() > 2])>
                @foreach (config('dpsg.home_highlights') as $slug => $tile)
                    @if ($highlight = $highlightPages->get($slug))
                        <article class="flex flex-col bg-white p-6 shadow-md">
                            @if ($tile['image'] ?? null)
                                <picture class="-mx-6 -mt-6 mb-4 block bg-[#eff2f7] px-6 py-4">
                                    <source type="image/webp" srcset="{{ asset($tile['image'].'.webp') }}" />
                                    <img src="{{ asset($tile['image'].'.png') }}" alt="" width="{{ $tile['image_size'][0] }}" height="{{ $tile['image_size'][1] }}" class="h-auto w-full" loading="lazy" />
                                </picture>
                            @endif
                            <h2 class="font-serif text-xl font-bold">{{ $highlight->title }}</h2>
                            <p class="mt-3 flex-1">{{ \Illuminate\Support\Str::limit(trim(\Illuminate\Support\Str::before(strip_tags($highlight->body), '[TODO')), 260) }}</p>
                            <a href="{{ url('/'.$highlight->slug) }}" class="mt-5 self-start border border-dpsg-rot px-3 py-1 text-sm text-dpsg-rot hover:bg-dpsg-rot hover:text-white">{{ $tile['button'] ?? 'Mehr erfahren' }}</a>
                        </article>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @php($serviceLinks = [
            ['url' => '/'.config('dpsg.site.office.page'), 'label' => config('dpsg.site.office.label'), 'icon' => 'heroicon-o-building-office-2'],
            ['url' => '/stufen', 'label' => 'Die Stufen', 'icon' => 'heroicon-o-user-group'],
            ['url' => '/'.config('dpsg.directory.path'), 'label' => config('dpsg.directory.title'), 'icon' => 'heroicon-o-map-pin'],
            ['url' => '/aktuelles', 'label' => 'Aktuelles', 'icon' => 'heroicon-o-newspaper'],
        ])
        {{-- Nur, was es in der Ausbaustufe gibt (Vorstand und Stufen ab 2, Aktuelles ab 3). --}}
        @php($serviceLinks = array_values(array_filter($serviceLinks, fn ($l) => $l['url'] !== '/' && filled($l['label'])
            && (\App\Support\Ausbau::gruppen() || ! in_array($l['url'], ['/'.config('dpsg.site.office.page'), '/stufen'], true))
            && (\App\Support\Ausbau::termine() || $l['url'] !== '/aktuelles'))))
        @foreach (config('dpsg.home_highlights') as $slug => $tile)
            @if (isset($tile['service']) && $highlightPages->has($slug))
                @php($serviceLinks[] = ['url' => '/'.$slug] + $tile['service'])
            @endif
        @endforeach
    @if ($serviceLinks !== [])
    <section class="mx-auto mt-20 max-w-6xl px-4" aria-labelledby="service-heading">
        @include('partials.section-heading', ['id' => 'service-heading', 'title' => 'Service'])

        <div @class(['grid grid-cols-2 gap-5 sm:grid-cols-3', 'lg:grid-cols-6' => count($serviceLinks) <= 6, 'lg:grid-cols-7' => count($serviceLinks) > 6])>
            @foreach ($serviceLinks as $link)
                <a href="{{ url($link['url']) }}" class="group flex aspect-square flex-col items-center justify-center gap-3 bg-white p-4 text-center shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                    <x-dynamic-component :component="$link['icon']" class="h-10 w-10 text-dpsg-rot" aria-hidden="true" />
                    <span class="group-hover:text-dpsg-rot">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
    @endif
@endsection
