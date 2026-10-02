@extends('layouts.app')

@section('seo_title', ($landing?->seo_title ?: $config['label']).' – DPSG Diözesanverband Trier')
@if ($landing?->seo_description)
    @section('seo_description', $landing->seo_description)
@endif
@if ($canonical)
    @section('canonical', $canonical)
@endif

@section('hero')
    <section class="relative flex h-64 items-center overflow-hidden bg-fach-internationales md:h-96">
        @if ($landing?->coverMedia)
            <x-picture :media="$landing->coverMedia" loading="eager" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/30 to-transparent" aria-hidden="true"></div>
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-fach-internationales via-fach-internationales to-dpsg-blau" aria-hidden="true"></div>
        @endif
        <div class="relative mx-auto w-full max-w-6xl px-4 pt-10 md:pl-32">
            <p class="text-sm font-bold uppercase tracking-wider text-white/80">DPSG Diözesanverband Trier</p>
            <h1 class="mt-1 max-w-2xl font-serif text-4xl font-bold text-white drop-shadow md:text-5xl">{{ $landing?->title ?? $config['label'] }}</h1>
            @if ($landing?->seo_description)
                <p class="mt-3 max-w-xl text-lg text-white drop-shadow">{{ $landing->seo_description }}</p>
            @endif
            <span class="mt-5 block h-1.5 w-40 rounded-full bg-dpsg-yellow" aria-hidden="true"></span>
        </div>
    </section>
@endsection

@section('content')
    @include('sections.nav', ['section' => $section, 'current' => $config['landing']])

    <div class="mx-auto max-w-6xl px-4">
        @if ($landing)
            <section class="mt-12 grid items-center gap-8 bg-white p-8 shadow-lg md:grid-cols-5">
                @if ($config['logo'] ?? null)
                    <picture class="md:order-last md:col-span-2">
                        <source type="image/webp" srcset="{{ asset($config['logo'].'.webp') }}" />
                        <img src="{{ asset($config['logo'].'.png') }}" alt="{{ $config['logo_alt'] ?? '' }}" width="744" height="335" class="mx-auto h-auto w-full max-w-sm" />
                    </picture>
                @endif
                <div @class(['prose max-w-none prose-headings:font-serif prose-a:text-dpsg-rot', 'md:col-span-3' => $config['logo'] ?? null, 'md:col-span-5' => ! ($config['logo'] ?? null)])>{!! str($landing->body)->sanitizeHtml() !!}</div>
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
                                    <a href="mailto:{{ $person->email ?: config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ $person->email ?: config('dpsg.contact.email') }}</a>
                                </p>
                                <a href="{{ $person->pageUrl() }}" class="mt-1 inline-block text-sm underline hover:text-dpsg-rot">Mehr über {{ \Illuminate\Support\Str::before($person->name, ' ') }}</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($pages->isNotEmpty())
            <section class="mt-16" aria-labelledby="themen-heading">
                @include('partials.section-heading', ['id' => 'themen-heading', 'title' => 'Projekte & Themen'])
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pages as $sectionPage)
                        <a href="{{ url('/'.$sectionPage->slug) }}" class="group flex flex-col border-t-4 border-fach-internationales bg-white p-6 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                            <h3 class="font-serif text-lg font-bold group-hover:text-fach-internationales">{{ $sectionPage->title }}</h3>
                            <p class="mt-2 flex-1 text-sm">{{ \Illuminate\Support\Str::limit(trim(\Illuminate\Support\Str::before(strip_tags($sectionPage->body), '[TODO')), 160) }}</p>
                            <span class="mt-4 text-sm text-dpsg-rot">Weiterlesen</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="mt-16" aria-labelledby="sektion-aktuelles">
            @include('partials.section-heading', ['id' => 'sektion-aktuelles', 'title' => 'Aktuelles'])
            @if ($posts->isEmpty())
                <p class="text-text-secondary">Noch keine Meldungen aus der {{ $config['label'] }}.</p>
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
