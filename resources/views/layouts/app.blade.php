<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('seo_title', $title ?? 'DPSG Diözesanverband Trier')</title>
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')" />
    @endif
    @hasSection('seo_description')
        <meta name="description" content="@yield('seo_description')" />
    @endif
    <link rel="icon" href="{{ asset('favicon.ico') }}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
{{-- Strukturprinzip nach bistum-trier.de (Pflichtenheft 7.1): schmale
     Servicezeile mit hängendem Logo-Badge, Hero bzw. Kopfband, darüber
     schwebende Hauptnavigation, mehrspaltiger Footer. Farben/Schriften
     ausschließlich DPSG-CD (7.2). --}}
<body class="min-h-screen flex flex-col bg-surface font-sans text-text" x-data="{ navOpen: false, scrolled: false }" @scroll.window.throttle.50ms="scrolled = window.scrollY > 40">
    <a href="#inhalt" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:bg-white focus:px-4 focus:py-2 focus:text-dpsg-blau">Zum Inhalt springen</a>

    @php($isHome = request()->routeIs('home'))

    {{-- Mobil bleibt die blaue Kopfzeile beim Scrollen oben stehen; das
         hängende Logo schrumpft dann in die Zeile hinein. --}}
    <header class="sticky top-0 z-40 bg-dpsg-blau text-white md:relative md:z-30">
        <div class="relative mx-auto flex h-12 max-w-6xl items-center justify-end gap-6 px-4">
            <a href="{{ url('/') }}" class="absolute left-4 top-0 rounded-b-lg bg-white px-3 shadow-lg transition-all duration-200 md:pb-3 md:pt-2" :class="scrolled ? 'pb-1 pt-1' : 'pb-3 pt-2'">
                <img src="{{ asset('images/logo-dv-trier.png') }}" alt="DPSG Diözesanverband Trier – zur Startseite" class="h-14 w-auto transition-all duration-200 md:h-20" :class="scrolled && 'max-md:h-10'" width="300" height="223" />
            </a>

            <nav aria-label="Service" class="hidden text-sm md:block">
                <ul class="flex gap-6">
                    <li><a href="{{ url('/kontakt') }}" class="hover:underline">Kontakt</a></li>
                    <li><a href="{{ url('/dioezesanbuero') }}" class="hover:underline">Diözesanbüro</a></li>
                    <li><a href="https://www.dpsg.de" target="_blank" rel="noopener" class="hover:underline">dpsg.de</a></li>
                </ul>
            </nav>

            <button type="button" class="md:hidden" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-controls="mobile-nav">
                <span class="sr-only">Menü</span>
                <x-heroicon-o-bars-3 class="h-7 w-7" x-show="!navOpen" />
                <x-heroicon-o-x-mark class="h-7 w-7" x-show="navOpen" x-cloak />
            </button>
        </div>

        <nav id="mobile-nav" aria-label="Hauptnavigation (mobil)" class="border-t border-white/20 bg-white text-text shadow-lg md:hidden" x-show="navOpen" x-cloak>
            <ul class="divide-y divide-surface-muted pt-12 font-bold">
                <li><a href="{{ url('/') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => $isHome])>Start</a></li>
                @foreach ($navPages ?? [] as $navPage)
                    <li>
                        <a href="{{ url('/'.$navPage->slug) }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is($navPage->slug)])>{{ $navPage->title }}</a>
                        @if ($navPage->navChildren->isNotEmpty())
                            <ul class="-mt-2 pb-2 font-normal">
                                @foreach ($navPage->navChildren as $child)
                                    <li><a href="{{ url('/'.$child->slug) }}" @class(['block py-1.5 pr-4 pl-8', 'text-dpsg-rot font-bold' => request()->is($child->slug)])>{{ $child->title }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
                <li><a href="{{ url('/bezirke-staemme') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('bezirke-staemme')])>Bezirke &amp; Stämme</a></li>
                <li><a href="{{ url('/veranstaltungen') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('veranstaltungen*')])>Veranstaltungen</a></li>
                <li><a href="{{ url('/kalender') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('kalender')])>Kalender</a></li>
                <li><a href="{{ url('/aktuelles') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('aktuelles*')])>Aktuelles</a></li>
                <li><a href="{{ url('/kontakt') }}" class="block px-4 py-3">Kontakt</a></li>
            </ul>
        </nav>
    </header>

    @hasSection('hero')
        @yield('hero')
    @else
        <div class="h-20 bg-dpsg-blau md:h-28" aria-hidden="true"></div>
    @endif

    {{-- Schwebende Hauptnavigation wie auf bistum-trier.de: liegt zunächst
         halb über dem Hero und bleibt beim Scrollen oben kleben. Sobald sie
         klebt, wird sie zur durchgehenden weißen Leiste mit kleinem Logo.
         Der Sentinel davor meldet, wann die Leiste oben angekommen ist. --}}
    <div class="hidden md:-mt-7 md:block" aria-hidden="true"
         x-data
         x-init="new IntersectionObserver(([e]) => $dispatch('nav-stuck', e.boundingClientRect.top < 0 && ! e.isIntersecting)).observe($el)"></div>
    <div class="sticky top-0 z-40 hidden w-full transition-[background-color,box-shadow] duration-200 md:block"
         x-data="{ stuck: false }"
         @nav-stuck.window="stuck = $event.detail"
         :class="stuck && 'bg-white shadow-md'">
        <nav aria-label="Hauptnavigation" class="mx-auto max-w-6xl px-4">
            <ul class="flex items-center bg-white text-sm font-bold lg:whitespace-nowrap transition-[padding,box-shadow] duration-200" :class="stuck ? 'py-2' : 'py-3 shadow-lg'">
                <li class="hidden shrink-0 lg:block" x-show="stuck" x-transition.opacity x-cloak>
                    <a href="{{ url('/') }}" class="block pr-4" tabindex="-1" aria-hidden="true">
                        <img src="{{ asset('images/logo-dv-trier.png') }}" alt="" class="h-9 w-auto" width="300" height="223" />
                    </a>
                </li>
                <li class="flex-1 text-center">
                    <a href="{{ url('/') }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => $isHome]) @if ($isHome) aria-current="page" @endif>Start</a>
                </li>
                @foreach ($navPages ?? [] as $navPage)
                    @php($children = $navPage->navChildren)
                    @php($current = request()->is($navPage->slug))
                    @php($active = $current || $children->contains(fn ($child) => request()->is($child->slug)))
                    @if ($children->isEmpty())
                        <li class="flex-1 border-l border-dpsg-mittelbeige text-center">
                            <a href="{{ url('/'.$navPage->slug) }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => $active]) @if ($current) aria-current="page" @endif>{{ $navPage->title }}</a>
                        </li>
                    @else
                        {{-- Unterpunkte: aufklappen bei Maus darüber, per Pfeil-Knopf
                             (Tastatur/Touch) oder Fokus; Esc schließt. --}}
                        <li class="relative flex-1 border-l border-dpsg-mittelbeige text-center"
                            x-data="{ open: false }"
                            @mouseenter="open = true" @mouseleave="open = false"
                            @keydown.escape="open = false; $refs.toggle.focus()"
                            @focusout="$el.contains($event.relatedTarget) || (open = false)">
                            <div class="flex items-center justify-center">
                                <a href="{{ url('/'.$navPage->slug) }}" @class(['block py-1 pl-3 hover:text-dpsg-rot', 'text-dpsg-rot' => $active]) @if ($current) aria-current="page" @endif>{{ $navPage->title }}</a>
                                <button type="button" x-ref="toggle" class="px-1 py-1 hover:text-dpsg-rot" @click="open = ! open" x-bind:aria-expanded="open.toString()" aria-expanded="false" aria-controls="subnav-{{ $navPage->slug }}">
                                    <span class="sr-only">Unterpunkte von {{ $navPage->title }}</span>
                                    <x-heroicon-m-chevron-down class="h-4 w-4 transition" x-bind:class="open && 'rotate-180'" aria-hidden="true" />
                                </button>
                            </div>
                            <div id="subnav-{{ $navPage->slug }}" class="absolute left-1/2 top-full z-50 -translate-x-1/2 pt-3" x-show="open" x-transition.opacity x-cloak>
                                <ul class="min-w-60 border-t-4 border-dpsg-rot bg-white py-2 text-left whitespace-nowrap shadow-xl">
                                    <li><a href="{{ url('/'.$navPage->slug) }}" @class(['block px-4 py-2 text-xs uppercase tracking-wide text-text-secondary hover:text-dpsg-rot', 'text-dpsg-rot' => $current])>Übersicht {{ $navPage->title }}</a></li>
                                    @foreach ($children as $child)
                                        @php($childActive = request()->is($child->slug))
                                        <li>
                                            <a href="{{ url('/'.$child->slug) }}" @class(['flex items-center gap-2 px-4 py-2 hover:bg-surface-muted hover:text-dpsg-rot', 'text-dpsg-rot' => $childActive, 'font-normal' => ! $childActive]) @if ($childActive) aria-current="page" @endif>
                                                @if ($child->stage)
                                                    <span @class([
                                                        'h-2.5 w-2.5 shrink-0 rounded-full',
                                                        'bg-stufe-biber' => $child->stage === 'biber',
                                                        'bg-stufe-woelflinge' => $child->stage === 'woelflinge',
                                                        'bg-stufe-jupfi' => $child->stage === 'jupfi',
                                                        'bg-stufe-pfadi' => $child->stage === 'pfadi',
                                                        'bg-stufe-rover' => $child->stage === 'rover',
                                                    ]) aria-hidden="true"></span>
                                                @elseif ($child->section && config('dpsg.sections.'.$child->section.'.color'))
                                                    <span class="fach-{{ config('dpsg.sections.'.$child->section.'.color') }} h-2.5 w-2.5 shrink-0 rounded-full bg-(--fach)" aria-hidden="true"></span>
                                                @endif
                                                {{ $child->title }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @endif
                @endforeach
                <li class="flex-1 border-l border-dpsg-mittelbeige text-center">
                    <a href="{{ url('/bezirke-staemme') }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('bezirke-staemme')])>Bezirke &amp; Stämme</a>
                </li>
                <li class="flex-1 border-l border-dpsg-mittelbeige text-center">
                    <a href="{{ url('/veranstaltungen') }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('veranstaltungen*')])>Veranstaltungen</a>
                </li>
                <li class="flex-1 border-l border-dpsg-mittelbeige text-center">
                    <a href="{{ url('/kalender') }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('kalender')])>Kalender</a>
                </li>
                <li class="flex-1 border-l border-dpsg-mittelbeige text-center">
                    <a href="{{ url('/aktuelles') }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('aktuelles*')])>Aktuelles</a>
                </li>
            </ul>
        </nav>
    </div>

    <main id="inhalt" class="flex-1">
        @yield('content')
    </main>

    @php($contact = config('dpsg.contact'))
    <footer class="mt-20 bg-dpsg-hellbeige text-text">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <h2 class="mb-4 font-serif text-base font-bold">Verband</h2>
                <ul class="space-y-2">
                    @foreach ($navPages ?? [] as $navPage)
                        <li><a href="{{ url('/'.$navPage->slug) }}" class="hover:underline">{{ $navPage->title }}</a></li>
                        @foreach ($navPage->navChildren->whereNull('stage') as $child)
                            <li class="pl-3"><a href="{{ url('/'.$child->slug) }}" class="hover:underline">{{ $child->title }}</a></li>
                        @endforeach
                    @endforeach
                    <li><a href="{{ url('/bezirke-staemme') }}" class="hover:underline">Bezirke &amp; Stämme</a></li>
                    <li><a href="{{ url('/veranstaltungen') }}" class="hover:underline">Veranstaltungen</a></li>
                    <li><a href="{{ url('/kalender') }}" class="hover:underline">Kalender</a></li>
                    <li><a href="{{ url('/georgsbote') }}" class="hover:underline">Georgsbote</a></li>
                    <li><a href="{{ url('/aktuelles') }}" class="hover:underline">Aktuelles</a></li>
                    <li><a href="https://www.dpsg.de" target="_blank" rel="noopener" class="hover:underline">DPSG Bundesebene</a></li>
                    <li><a href="https://www.bistum-trier.de" target="_blank" rel="noopener" class="hover:underline">Bistum Trier</a></li>
                </ul>
            </div>
            <div>
                <h2 class="mb-4 font-serif text-base font-bold">Stufen</h2>
                <ul class="space-y-2">
                    @foreach ($stagePages ?? [] as $stagePage)
                        <li><a href="{{ url('/'.$stagePage->slug) }}" class="hover:underline">{{ config('dpsg.stages.'.$stagePage->stage.'.label') }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="mb-4 font-serif text-base font-bold">Service</h2>
                <ul class="space-y-2">
                    <li><a href="{{ url('/kontakt') }}" class="hover:underline">Kontakt</a></li>
                    <li><a href="{{ url('/dioezesanbuero') }}" class="hover:underline">Diözesanbüro</a></li>
                    <li><a href="{{ url('/impressum') }}" class="hover:underline">Impressum</a></li>
                    <li><a href="{{ url('/datenschutz') }}" class="hover:underline">Datenschutz</a></li>
                    <li>
                        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-consent-settings'))" class="hover:underline">Datenschutz-Einstellungen</button>
                    </li>
                    <li><a href="{{ url('/admin') }}" class="hover:underline">Redaktions-Login</a></li>
                </ul>
            </div>
            <div>
                <h2 class="mb-4 font-serif text-base font-bold">{{ $contact['name'] }}</h2>
                <p class="font-bold">{{ $contact['office'] }}</p>
                <p>{{ $contact['street'] }}<br />{{ $contact['city'] }}</p>
                <ul class="mt-3 space-y-2">
                    <li class="flex items-center gap-2"><x-heroicon-o-phone class="h-4 w-4 shrink-0" aria-hidden="true" /><a href="tel:{{ $contact['phone_href'] }}" class="hover:underline">{{ $contact['phone'] }}</a></li>
                    <li class="flex items-center gap-2"><x-heroicon-o-envelope class="h-4 w-4 shrink-0" aria-hidden="true" /><a href="mailto:{{ $contact['email'] }}" class="hover:underline">{{ $contact['email'] }}</a></li>
                </ul>
            </div>
        </div>
        <div class="bg-dpsg-blau text-white">
            <div class="mx-auto flex max-w-6xl flex-wrap gap-x-6 gap-y-2 px-4 py-4 text-sm">
                <span>© {{ date('Y') }} {{ $contact['name'] }}</span>
                <a href="{{ url('/impressum') }}" class="hover:underline">Impressum</a>
                <a href="{{ url('/datenschutz') }}" class="hover:underline">Datenschutz</a>
                <span class="sm:ml-auto">Stufen-Maskottchen und Rover-Lilie: DPSG (dpsg.de, rover.de)</span>
            </div>
        </div>
    </footer>

    @include('partials.consent-banner')
</body>
</html>
