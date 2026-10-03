<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('seo_title', $title ?? 'DPSG Diözesanverband Trier')</title>
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')" />
    @endif
    {{-- Ohne eigene Beschreibung eine allgemeine, damit Suchmaschinen nicht raten. --}}
    <meta name="description" content="@yield('seo_description', 'Deutsche Pfadfinderschaft Sankt Georg im Bistum Trier: Neuigkeiten, Veranstaltungen, Stufen und Stämme zwischen Mosel, Eifel, Hunsrück und Saarland.')" />
    <link rel="icon" href="{{ asset('favicon.ico') }}" />
    <link rel="alternate" type="application/rss+xml" title="DPSG Diözesanverband Trier – Aktuelles" href="{{ route('feed') }}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
{{-- Strukturprinzip nach bistum-trier.de (Pflichtenheft 7.1): schmale
     Servicezeile mit hängendem Logo-Badge, Hero bzw. Kopfband, darüber
     schwebende Hauptnavigation, mehrspaltiger Footer. Farben/Schriften
     ausschließlich DPSG-CD (7.2). --}}
<body class="min-h-screen flex flex-col bg-surface font-sans text-text" x-data="{ navOpen: false, scrolled: false }" @scroll.window.throttle.50ms="scrolled = window.scrollY > 40"
      :class="navOpen && 'max-md:overflow-hidden'" @keydown.escape.window="navOpen = false">
    <a href="#inhalt" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:bg-white focus:px-4 focus:py-2 focus:text-dpsg-blau">Zum Inhalt springen</a>


    {{-- Mobil bleibt die blaue Kopfzeile beim Scrollen oben stehen; das
         hängende Logo schrumpft dann (und bei offenem Menü) in die Zeile. --}}
    <header class="sticky top-0 z-40 bg-dpsg-blau text-white md:relative md:z-30">
        <div class="relative mx-auto flex h-12 max-w-6xl items-center justify-end gap-6 px-4">
            <a href="{{ url('/') }}" class="absolute left-4 top-0 rounded-b-lg bg-white px-3 shadow-lg transition-all duration-200 md:pb-3 md:pt-2" :class="(scrolled || navOpen) ? 'pb-1 pt-1' : 'pb-3 pt-2'">
                <x-static-image src="images/logo-dv-trier.png" alt="DPSG Diözesanverband Trier – zur Startseite" class="h-16 w-auto transition-all duration-200 md:h-28" x-bind:class="(scrolled || navOpen) && 'max-md:h-10'" width="300" height="223" fetchpriority="high" />
            </a>

            <nav aria-label="Service" class="hidden text-sm md:block">
                <ul class="flex gap-6">
                    <li><a href="{{ url('/kontakt') }}" class="hover:underline">Kontakt</a></li>
                    <li><a href="{{ url('/dioezesanbuero') }}" class="hover:underline">Diözesanbüro</a></li>
                    <li><a href="{{ url('/meine-anmeldungen') }}" class="hover:underline">Meine Anmeldungen</a></li>
                    <li><a href="https://www.dpsg.de" target="_blank" rel="noopener" class="hover:underline">dpsg.de</a></li>
                </ul>
            </nav>

            <button type="button" class="md:hidden" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-controls="mobile-nav">
                <span class="sr-only">Menü</span>
                <x-heroicon-o-bars-3 class="h-7 w-7" x-show="!navOpen" />
                <x-heroicon-o-x-mark class="h-7 w-7" x-show="navOpen" x-cloak />
            </button>
        </div>

        {{-- Längeres Menü als der Bildschirm: eigener Scrollbereich bis unter
             die Kopfzeile, die Seite dahinter ist so lange gesperrt. --}}
        <nav id="mobile-nav" aria-label="Hauptnavigation (mobil)" class="max-h-[calc(100dvh-3rem)] overflow-y-auto overscroll-contain border-t border-white/20 bg-white text-text shadow-lg md:hidden" x-show="navOpen" x-cloak>
            <ul class="divide-y divide-surface-muted pt-2 pb-[calc(2rem+env(safe-area-inset-bottom))] font-bold">
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
                <li>
                    <a href="{{ url('/veranstaltungen') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('veranstaltungen*')])>Veranstaltungen</a>
                    <ul class="-mt-2 pb-2 font-normal">
                        <li><a href="{{ url('/meine-anmeldungen') }}" @class(['block py-1.5 pr-4 pl-8', 'text-dpsg-rot font-bold' => request()->is('meine-anmeldungen*')])>Meine Anmeldungen</a></li>
                    </ul>
                </li>
                <li><a href="{{ url('/kalender') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('kalender')])>Kalender</a></li>
                <li><a href="{{ url('/aktuelles') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('aktuelles*')])>Aktuelles</a></li>
                <li><a href="{{ url('/kontakt') }}" class="block px-4 py-3">Kontakt</a></li>
            </ul>
        </nav>
    </header>

    @hasSection('hero')
        @yield('hero')
    @else
        {{-- Seiten ohne Hero: blauer Streifen etwas höher als das hängende
             Logo (Desktop 132 px), damit die Menüleiste nicht am Logo klebt. --}}
        <div class="h-20 bg-dpsg-blau md:h-36" aria-hidden="true"></div>
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
                {{-- Kein Menüpunkt „Start“: das Logo führt zur Startseite. Klebt die
                     Leiste oben, übernimmt das kleine Logo diese Aufgabe. --}}
                <li class="shrink-0" x-show="stuck" x-transition.opacity x-cloak>
                    <a href="{{ url('/') }}" class="block pr-4">
                        <x-static-image src="images/logo-dv-trier.png" alt="DPSG Diözesanverband Trier – zur Startseite" class="h-9 w-auto" width="300" height="223" loading="lazy" />
                    </a>
                </li>
                @foreach ($navPages ?? [] as $navPage)
                    @php($children = $navPage->navChildren)
                    @php($current = request()->is($navPage->slug))
                    @php($active = $current || $children->contains(fn ($child) => request()->is($child->slug)))
                    @if ($children->isEmpty())
                        <li @class(['flex-1 text-center', 'border-l border-dpsg-mittelbeige' => ! $loop->first])>
                            <a href="{{ url('/'.$navPage->slug) }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => $active]) @if ($current) aria-current="page" @endif>{{ $navPage->title }}</a>
                        </li>
                    @else
                        {{-- Unterpunkte: aufklappen bei Maus darüber, per Pfeil-Knopf
                             (Tastatur/Touch) oder Fokus; Esc schließt. --}}
                        <li @class(['relative flex-1 text-center', 'border-l border-dpsg-mittelbeige' => ! $loop->first])
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
                {{-- Veranstaltungen mit Unterpunkt „Meine Anmeldungen“; gleiches
                     Aufklappverhalten wie bei den Seiten oben. --}}
                @php($eventsCurrent = request()->is('veranstaltungen'))
                @php($mineActive = request()->is('meine-anmeldungen*'))
                <li class="relative flex-1 border-l border-dpsg-mittelbeige text-center"
                    x-data="{ open: false }"
                    @mouseenter="open = true" @mouseleave="open = false"
                    @keydown.escape="open = false; $refs.toggle.focus()"
                    @focusout="$el.contains($event.relatedTarget) || (open = false)">
                    <div class="flex items-center justify-center">
                        <a href="{{ url('/veranstaltungen') }}" @class(['block py-1 pl-3 hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('veranstaltungen*') || $mineActive]) @if ($eventsCurrent) aria-current="page" @endif>Veranstaltungen</a>
                        <button type="button" x-ref="toggle" class="px-1 py-1 hover:text-dpsg-rot" @click="open = ! open" x-bind:aria-expanded="open.toString()" aria-expanded="false" aria-controls="subnav-veranstaltungen">
                            <span class="sr-only">Unterpunkte von Veranstaltungen</span>
                            <x-heroicon-m-chevron-down class="h-4 w-4 transition" x-bind:class="open && 'rotate-180'" aria-hidden="true" />
                        </button>
                    </div>
                    <div id="subnav-veranstaltungen" class="absolute left-1/2 top-full z-50 -translate-x-1/2 pt-3" x-show="open" x-transition.opacity x-cloak>
                        <ul class="min-w-60 border-t-4 border-dpsg-rot bg-white py-2 text-left whitespace-nowrap shadow-xl">
                            <li><a href="{{ url('/veranstaltungen') }}" @class(['block px-4 py-2 text-xs uppercase tracking-wide text-text-secondary hover:text-dpsg-rot', 'text-dpsg-rot' => $eventsCurrent])>Übersicht Veranstaltungen</a></li>
                            <li><a href="{{ url('/meine-anmeldungen') }}" @class(['flex items-center gap-2 px-4 py-2 hover:bg-surface-muted hover:text-dpsg-rot', 'text-dpsg-rot' => $mineActive, 'font-normal' => ! $mineActive]) @if ($mineActive) aria-current="page" @endif>Meine Anmeldungen</a></li>
                        </ul>
                    </div>
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
    {{-- Schlanker Footer (Rückmeldung Talia): kein zweites Menü, nur
         Kontakt, Service und der Georgsbrief. --}}
    <footer class="mt-20 bg-dpsg-hellbeige text-text">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 text-sm md:grid-cols-3">
            <div>
                <h2 class="mb-4 font-serif text-base font-bold">{{ $contact['name'] }}</h2>
                <p class="font-bold">{{ $contact['office'] }}</p>
                <p>{{ $contact['street'] }}<br />{{ $contact['city'] }}</p>
                <ul class="mt-3 space-y-2">
                    <li class="flex items-center gap-2"><x-heroicon-o-phone class="h-4 w-4 shrink-0" aria-hidden="true" /><a href="tel:{{ $contact['phone_href'] }}" class="hover:underline">{{ $contact['phone'] }}</a></li>
                    <li class="flex items-center gap-2"><x-heroicon-o-envelope class="h-4 w-4 shrink-0" aria-hidden="true" /><a href="mailto:{{ $contact['email'] }}" class="hover:underline">{{ $contact['email'] }}</a></li>
                </ul>
            </div>
            <div>
                <h2 class="mb-4 font-serif text-base font-bold">Service</h2>
                <ul class="space-y-2">
                    <li><a href="{{ url('/kontakt') }}" class="hover:underline">Kontakt</a></li>
                    <li><a href="{{ url('/dioezesanbuero') }}" class="hover:underline">Diözesanbüro</a></li>
                    <li><a href="{{ url('/meine-anmeldungen') }}" class="hover:underline">Meine Anmeldungen</a></li>
                    <li>
                        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-consent-settings'))" class="hover:underline">Datenschutz-Einstellungen</button>
                    </li>
                    <li><a href="{{ url('/admin') }}" class="hover:underline">Redaktions-Login</a></li>
                    <li><a href="{{ url('/status') }}" class="hover:underline">Systemstatus</a></li>
                </ul>
                <h2 class="mb-3 mt-6 font-serif text-base font-bold">DPSG im Netz</h2>
                <ul class="space-y-2">
                    <li><a href="https://www.dpsg.de" target="_blank" rel="noopener" class="hover:underline">dpsg.de – Bundesebene</a></li>
                    <li><a href="https://www.bistum-trier.de" target="_blank" rel="noopener" class="hover:underline">Bistum Trier</a></li>
                </ul>
            </div>
            <div>
                <a href="{{ route('georgsbrief.index') }}" class="group block overflow-hidden bg-white shadow-sm hover:shadow-md">
                    <span class="block bg-[#eff2f7] px-4 py-3">
                        <picture>
                            <source type="image/webp" srcset="{{ asset('images/georgsbrief-logo.webp') }}" />
                            <img src="{{ asset('images/georgsbrief-logo.png') }}" alt="Georgsbrief – der DPSG Trier Newsletter" width="970" height="251" class="h-auto w-full" loading="lazy" />
                        </picture>
                    </span>
                    <span class="block p-4">
                        Termine und Neuigkeiten aus dem Diözesanverband direkt ins Postfach.
                        <span class="mt-2 block font-bold text-dpsg-rot group-hover:underline">Georgsbrief abonnieren →</span>
                    </span>
                </a>
            </div>
        </div>
        <div class="bg-dpsg-blau text-white">
            <div class="mx-auto flex max-w-6xl flex-wrap gap-x-6 gap-y-2 px-4 py-4 text-sm">
                <span>© {{ date('Y') }} {{ $contact['name'] }}</span>
                <a href="{{ url('/impressum') }}" class="hover:underline">Impressum</a>
                <a href="{{ url('/datenschutz') }}" class="hover:underline">Datenschutz</a>
                <span class="sm:ml-auto">Stufen-Maskottchen, Rover- und Inklusions-Lilie: DPSG (dpsg.de, rover.de)</span>
            </div>
        </div>
    </footer>

    @include('partials.consent-banner')
</body>
</html>
