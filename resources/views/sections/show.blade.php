@extends('layouts.app')

@section('seo_title', ($landing?->seo_title ?: $config['label']).' – '.config('dpsg.site.name'))
@if ($landing?->seo_description)
    @section('seo_description', $landing->seo_description)
@endif
@if ($canonical)
    @section('canonical', $canonical)
@endif

@php($fach = 'fach-'.($config['color'] ?? 'internationales'))
@php($heroInk = $landing?->coverMedia ? 'text-white' : 'text-(--fach-ink)')
{{-- Quer-Logos (Amistad) stehen neben dem Text, quadratische Zeichen (Lilie) im Kopfband. --}}
@php($squareLogo = ($config['logo'] ?? null) && ($config['logo_size'][0] ?? 744) <= ($config['logo_size'][1] ?? 335) * 1.3)
@php($cardLogo = ($config['logo'] ?? null) && ! $squareLogo)

@section('hero')
    <section class="{{ $fach }} relative flex h-64 items-center overflow-hidden bg-(--fach) md:h-96">
        @if ($landing?->coverMedia)
            <x-picture :media="$landing->coverMedia" loading="eager" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/30 to-transparent" aria-hidden="true"></div>
        @else
            <div class="stufe-pattern absolute inset-0 [--stufe-ink:var(--fach-ink)]" aria-hidden="true"></div>
        @endif
        <div class="relative mx-auto w-full max-w-6xl px-4 pt-10 md:pl-32">
            <p class="{{ $heroInk }} text-sm font-bold uppercase tracking-wider opacity-80">{{ $config['kind'] ?? 'Fachbereich' }} · {{ config('dpsg.site.name') }}</p>
            <h1 @class([$heroInk, 'mt-1 max-w-2xl font-serif text-4xl font-bold md:text-5xl', 'drop-shadow' => $landing?->coverMedia])>{{ $landing?->title ?? $config['label'] }}</h1>
            @if ($landing?->seo_description)
                <p @class([$heroInk, 'mt-3 max-w-xl text-lg', 'drop-shadow' => $landing?->coverMedia])>{{ $landing->seo_description }}</p>
            @endif
            <span @class(['mt-5 block h-1.5 w-40 rounded-full', 'bg-dpsg-yellow' => ($config['color'] ?? null) !== 'inklusion', 'bg-dpsg-blau' => ($config['color'] ?? null) === 'inklusion']) aria-hidden="true"></span>
        </div>
        {{-- Quadratisches Fachbereichszeichen (z. B. Inklusions-Lilie) groß im Kopfband --}}
        @if ($squareLogo)
            <div class="absolute right-[max(1rem,calc((100vw-72rem)/2+1rem))] top-1/2 hidden h-56 w-56 -translate-y-1/2 items-center justify-center rounded-full bg-white p-8 shadow-xl ring-8 ring-white/30 md:flex" aria-hidden="true">
                <picture>
                    <source type="image/webp" srcset="{{ asset($config['logo'].'.webp') }}" />
                    <img src="{{ asset($config['logo'].'.png') }}" alt="" width="{{ $config['logo_size'][0] }}" height="{{ $config['logo_size'][1] }}" class="h-auto w-full" />
                </picture>
            </div>
        @endif
    </section>
@endsection

@section('content')
    @include('sections.nav', ['section' => $section, 'current' => $config['landing']])

    <div class="mx-auto max-w-6xl px-4">
        @if ($landing)
            <section class="mt-12 grid items-center gap-8 bg-white p-8 shadow-lg md:grid-cols-5">
                @if ($squareLogo)
                    {{-- Mobil steht das Zeichen nicht im Kopfband, daher hier klein. --}}
                    <picture class="mx-auto block w-28 md:hidden">
                        <source type="image/webp" srcset="{{ asset($config['logo'].'.webp') }}" />
                        <img src="{{ asset($config['logo'].'.png') }}" alt="{{ $config['logo_alt'] ?? '' }}" width="{{ $config['logo_size'][0] }}" height="{{ $config['logo_size'][1] }}" class="h-auto w-full" />
                    </picture>
                @endif
                @if ($cardLogo)
                    <picture class="md:order-last md:col-span-2">
                        <source type="image/webp" srcset="{{ asset($config['logo'].'.webp') }}" />
                        <img src="{{ asset($config['logo'].'.png') }}" alt="{{ $config['logo_alt'] ?? '' }}" width="{{ $config['logo_size'][0] ?? 744 }}" height="{{ $config['logo_size'][1] ?? 335 }}" class="mx-auto h-auto w-full max-w-sm" />
                    </picture>
                @endif
                <div @class(['prose max-w-none prose-headings:font-serif prose-a:text-dpsg-rot', 'md:col-span-3' => $cardLogo, 'md:col-span-5' => ! $cardLogo])>{!! str($landing->body)->sanitizeHtml()->glossar()->embeds()->mailformular()->galerie() !!}</div>
            </section>
        @endif

        @if ($contacts->isNotEmpty())
            <section class="mt-16" aria-labelledby="ansprechperson-heading">
                @include('partials.section-heading', ['id' => 'ansprechperson-heading', 'title' => $contacts->count() > 1 ? 'Ansprechpersonen' : 'Ansprechperson'])
                <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($contacts as $person)
                        <li class="flex items-center gap-4 bg-white p-5 shadow-md">
                            @include('partials.person-avatar', ['person' => $person])
                            <div>
                                <p class="font-serif text-lg font-bold leading-tight">{{ $person->name }}</p>
                                @if ($person->role)<p class="text-sm text-text-secondary">{{ $person->role }}</p>@endif
                                <p class="mt-1 text-sm">
                                    <a href="{{ \App\Support\Mailformular::href($person->publicEmail() ?: config('dpsg.contact.email')) }}" class="break-all text-dpsg-rot underline">{{ $person->publicEmail() ?: config('dpsg.contact.email') }}</a>
                                </p>
                                <a href="{{ $person->pageUrl() }}" class="mt-1 inline-block text-sm underline hover:text-dpsg-rot">Mehr über {{ \Illuminate\Support\Str::before($person->name, ' ') }}</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if ($downloads->isNotEmpty())
            {{-- Dateien von der Download-Seite, die diesem Bereich zugeordnet sind --}}
            <section class="mt-16" aria-labelledby="dokumente-heading">
                @include('partials.section-heading', ['id' => 'dokumente-heading', 'title' => 'Dokumente'])
                <ul class="grid gap-4 sm:grid-cols-2">
                    @foreach ($downloads as $download)
                        <li>
                            <a href="{{ $download->url() }}" class="group flex h-full items-start gap-4 rounded-lg bg-white p-4 shadow-md hover:shadow-lg"
                               @if (in_array($download->extension(), ['pdf', 'jpg', 'jpeg', 'png'], true)) target="_blank" rel="noopener" @endif>
                                <span class="flex h-12 w-10 shrink-0 items-end justify-center rounded bg-dpsg-blau pb-1.5 text-[11px] font-bold text-white" aria-hidden="true">{{ $download->badge() }}</span>
                                <span class="min-w-0">
                                    <span class="block font-bold text-dpsg-blau group-hover:text-dpsg-rot">{{ $download->title }}</span>
                                    @if ($download->description)<span class="block text-sm text-text-secondary">{{ $download->description }}</span>@endif
                                    <span class="mt-1 block text-xs text-text-secondary">{{ $download->meta() }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-sm"><a href="{{ route('downloads.index') }}" class="underline hover:text-dpsg-rot">Alle Downloads</a></p>
            </section>
        @endif

        @if ($fak !== null)
            {{-- Facharbeitskreis (FAK) des Fachbereichs --}}
            <section id="fak" class="mt-16 scroll-mt-24" aria-labelledby="fak-heading">
                @include('partials.section-heading', ['id' => 'fak-heading', 'title' => 'Der Facharbeitskreis'])
                <p class="max-w-3xl text-text-secondary">
                    Im Facharbeitskreis (FAK) {{ $config['label'] }} gestalten Ehrenamtliche gemeinsam mit den Referent*innen die Arbeit des Fachbereichs im {{ config('dpsg.site.level') }}:
                    Sie planen Aktionen und Fortbildungen, entwickeln Material und unterstützen Stämme und Bezirke.
                </p>
                @if ($fak->isNotEmpty())
                    <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($fak as $person)
                            <li id="{{ $person->anchor() }}" class="flex items-center gap-4 bg-white p-5 shadow-md">
                                @include('partials.person-avatar', ['person' => $person])
                                <div>
                                    <p class="font-serif text-lg font-bold leading-tight">{{ $person->name }}</p>
                                    @if ($person->role)<p class="text-sm text-text-secondary">{{ $person->role }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-6 flex flex-wrap items-center gap-3 rounded-lg bg-surface-muted p-4 text-sm">
                    <x-heroicon-o-hand-raised class="h-5 w-5 shrink-0 text-dpsg-rot" aria-hidden="true" />
                    <span>Lust, im FAK {{ $config['label'] }} mitzuarbeiten? @include('partials.mitmachen-ansprechpersonen', ['mail' => $contacts->filter(fn ($p) => $p->publicEmail()), 'members' => $fak]).</span>
                </p>
            </section>
        @endif

        @if ($contactUrl = \App\Models\ContactTopic::urlFor('section', $section))
            <p class="mt-6 flex items-center gap-2 text-sm">
                <x-heroicon-o-envelope class="h-5 w-5 shrink-0 text-dpsg-rot" aria-hidden="true" />
                <span>Fragen oder Lust mitzumachen? <a href="{{ $contactUrl }}" class="font-bold text-dpsg-rot underline">Schreib uns über das Kontaktformular</a>.</span>
            </p>
        @endif

        @if ($pages->isNotEmpty())
            <section class="mt-16" aria-labelledby="themen-heading">
                @include('partials.section-heading', ['id' => 'themen-heading', 'title' => 'Projekte & Themen'])
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pages as $sectionPage)
                        <a href="{{ url('/'.$sectionPage->slug) }}" class="{{ $fach }} group flex flex-col border-t-4 border-(--fach) bg-white p-6 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                            <h3 class="font-serif text-lg font-bold group-hover:text-dpsg-rot">{{ $sectionPage->title }}</h3>
                            <p class="mt-2 flex-1 text-sm">{{ $sectionPage->seo_description ?: \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish(html_entity_decode(strip_tags(str_replace('<', ' <', \Illuminate\Support\Str::before((string) $sectionPage->body, '[TODO'))))), 160) }}</p>
                            <span class="mt-4 text-sm text-dpsg-rot">Weiterlesen</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @include('partials.instagram', ['ort' => 'section:'.$section, 'class' => 'mt-16'])

        <section class="mt-16" aria-labelledby="sektion-aktuelles">
            @include('partials.section-heading', ['id' => 'sektion-aktuelles', 'title' => 'Aktuelles'])
            @if ($posts->isEmpty())
                <p class="text-text-secondary">Noch keine Meldungen {{ match ($config['kind'] ?? 'Fachbereich') { 'Fachbereich' => 'aus dem Fachbereich', 'Thema' => 'zum Thema', default => 'von' } }} {{ $config['label'] }}.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($posts as $post)
                        <article class="flex flex-col bg-white shadow-md">
                            @if ($post->coverMedia)
                                <x-picture :media="$post->coverMedia" alt="" sizes="(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw" class="aspect-[16/9] w-full object-cover" />
                            @endif
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="font-serif text-lg font-bold text-dpsg-rot"><a href="{{ url('/aktuelles/'.$post->slug) }}" class="hover:underline">{{ $post->title }}</a></h3>
                                <p class="mt-1 text-xs text-text-secondary">{{ $post->published_at?->locale('de')->isoFormat('D. MMMM YYYY') }}</p>
                                <p class="mt-3 flex-1">{{ $post->teaser }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
                <a href="{{ route('sections.posts', $section) }}" class="mt-8 inline-block border border-dpsg-rot px-4 py-2 text-dpsg-rot hover:bg-dpsg-rot hover:text-white">Alle Meldungen</a>
            @endif
        </section>

        @if ($quietPages->isNotEmpty())
            <p class="mt-16 text-sm text-text-secondary">
                @foreach ($quietPages as $quietPage)
                    @if ($quietPage->slug === 'bolivien-spenden')
                        Du möchtest die Partnerschaft unterstützen? <a href="{{ url('/'.$quietPage->slug) }}" class="underline hover:text-dpsg-rot">Informationen zu Spenden</a>
                    @else
                        <a href="{{ url('/'.$quietPage->slug) }}" class="underline hover:text-dpsg-rot">{{ $quietPage->title }}</a>
                    @endif
                @endforeach
            </p>
        @endif
    </div>
@endsection
