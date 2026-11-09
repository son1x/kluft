<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('seo_title', $title ?? config('dpsg.site.name'))</title>
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')" />
    @elseif (request()->getHost() !== parse_url((string) config('app.url'), PHP_URL_HOST))
        {{-- Unterseiten unter einer Zusatzdomain (z. B. beraten-online.de/coaching): Suchmaschinen auf die Hauptadresse verweisen, sonst doppelte Inhalte. --}}
        <link rel="canonical" href="{{ rtrim((string) config('app.url'), '/').'/'.ltrim(request()->path(), '/') }}" />
    @endif
    {{-- Ohne eigene Beschreibung eine allgemeine, damit Suchmaschinen nicht raten. --}}
    <meta name="description" content="@yield('seo_description', config('dpsg.site.description'))" />
    @if (\App\Support\Testsystem::aktiv())
        <meta name="robots" content="noindex, nofollow" />
    @endif
    <link rel="icon" href="{{ asset(config('dpsg.site.favicon', 'favicon.ico')) }}" />
    @if (\App\Support\Ausbau::termine())
        <link rel="alternate" type="application/rss+xml" title="{{ config('dpsg.site.name') }} – Aktuelles" href="{{ route('feed') }}" />
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($stufenfarben = \App\Support\Site::stufenfarbenCss())
        <style>{!! $stufenfarben !!}</style>
    @endif
    @stack('head')
</head>
{{-- Strukturprinzip nach bistum-trier.de (Pflichtenheft 7.1): schmale
     Servicezeile mit hängendem Logo-Badge, Hero bzw. Kopfband, darüber
     schwebende Hauptnavigation, mehrspaltiger Footer. Farben/Schriften
     ausschließlich DPSG-CD (7.2). --}}
<body class="min-h-screen flex flex-col bg-surface font-sans text-text" x-data="{ navOpen: false, scrolled: false }" @scroll.window.throttle.50ms="scrolled = window.scrollY > 40"
      :class="navOpen && 'max-md:overflow-hidden'" @keydown.escape.window="navOpen = false">
    <a href="#inhalt" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:bg-white focus:px-4 focus:py-2 focus:text-dpsg-blau">Zum Inhalt springen</a>
    @if (auth()->check() && \App\Support\Wartung::aktiv())
        @include('kluft.wartung-band')
    @endif
    @if (\App\Support\Testsystem::aktiv() && ! request()->attributes->get('testsystem_extern'))
        {{-- Testsystem (docs/livegang.md): deutlich kennzeichnen, damit niemand hier Inhalte pflegt. --}}
        <div class="relative z-50 bg-dpsg-yellow text-text" role="note">
            <p class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-2 px-4 py-2 text-center text-sm">
                <strong>Testsystem.</strong>
                <span>Hier werden neue Funktionen ausprobiert; Änderungen gehen nicht auf die Website.</span>
                @if ($live = \App\Support\Testsystem::liveUrl())
                    <a href="{{ $live }}" class="font-bold underline">Zur Website {{ preg_replace('#^https://#', '', $live) }}</a>
                @endif
            </p>
        </div>
    @endif


    {{-- Mobil bleibt die blaue Kopfzeile beim Scrollen oben stehen; das
         hängende Logo schrumpft dann (und bei offenem Menü) in die Zeile. --}}
    <header class="sticky top-0 z-40 bg-dpsg-blau text-white md:relative md:z-30">
        <div class="relative mx-auto flex h-12 max-w-6xl items-center justify-end gap-6 px-4">
            <a href="{{ url('/') }}" class="absolute left-4 top-0 rounded-b-lg bg-white px-3 shadow-lg transition-all duration-200 md:pb-3 md:pt-2" :class="(scrolled || navOpen) ? 'pb-1 pt-1' : 'pb-3 pt-2'">
                <x-static-image :src="config('dpsg.site.logo')" :alt="config('dpsg.site.name').' – zur Startseite'" class="h-16 w-auto transition-all duration-200 md:h-28" x-bind:class="(scrolled || navOpen) && 'max-md:h-10'" :width="config('dpsg.site.logo_size')[0]" :height="config('dpsg.site.logo_size')[1]" fetchpriority="high" />
            </a>

            <nav aria-label="Service" class="hidden text-sm md:block">
                <ul class="flex gap-6">
                    <li><a href="{{ url('/kontakt') }}" class="hover:underline">Kontakt</a></li>
                    @if (\App\Support\Ausbau::gruppen())
                        <li><a href="{{ \App\Support\Site::officeUrl() }}" class="hover:underline">{{ \App\Support\Site::officeLabel() }}</a></li>
                    @endif
                    @if (\App\Support\Ausbau::anmeldungen())
                        <li><a href="{{ url('/meine-anmeldungen') }}" class="hover:underline">Meine Anmeldungen</a></li>
                    @endif
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
                @if (\App\Support\Ausbau::termine())
                    <li>
                        <a href="{{ url('/veranstaltungen') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('veranstaltungen*')])>{{ \App\Support\Site::termineLabel() }}</a>
                        @if (\App\Support\Ausbau::anmeldungen() || \App\Support\Site::kalenderUnterTerminen())
                            <ul class="-mt-2 pb-2 font-normal">
                                @if (\App\Support\Site::kalenderUnterTerminen())
                                    <li><a href="{{ url('/kalender') }}" @class(['block py-1.5 pr-4 pl-8', 'text-dpsg-rot font-bold' => request()->is('kalender')])>Kalender</a></li>
                                @endif
                                @if (\App\Support\Ausbau::anmeldungen())
                                    <li><a href="{{ url('/meine-anmeldungen') }}" @class(['block py-1.5 pr-4 pl-8', 'text-dpsg-rot font-bold' => request()->is('meine-anmeldungen*')])>Meine Anmeldungen</a></li>
                                @endif
                            </ul>
                        @endif
                    </li>
                    @unless (\App\Support\Site::kalenderUnterTerminen())
                        <li><a href="{{ url('/kalender') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('kalender')])>Kalender</a></li>
                    @endunless
                    <li><a href="{{ url('/aktuelles') }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is('aktuelles*')])>Aktuelles</a></li>
                @endif
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
                        <x-static-image :src="config('dpsg.site.logo')" :alt="config('dpsg.site.name').' – zur Startseite'" class="h-9 w-auto" :width="config('dpsg.site.logo_size')[0]" :height="config('dpsg.site.logo_size')[1]" loading="lazy" />
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
                @if (\App\Support\Ausbau::termine())
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
                            <a href="{{ url('/veranstaltungen') }}" @class(['block py-1 pl-3 hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('veranstaltungen*') || $mineActive || (\App\Support\Site::kalenderUnterTerminen() && request()->is('kalender'))]) @if ($eventsCurrent) aria-current="page" @endif>{{ \App\Support\Site::termineLabel() }}</a>
                            <button type="button" x-ref="toggle" class="px-1 py-1 hover:text-dpsg-rot" @click="open = ! open" x-bind:aria-expanded="open.toString()" aria-expanded="false" aria-controls="subnav-veranstaltungen">
                                <span class="sr-only">Unterpunkte von {{ \App\Support\Site::termineLabel() }}</span>
                                <x-heroicon-m-chevron-down class="h-4 w-4 transition" x-bind:class="open && 'rotate-180'" aria-hidden="true" />
                            </button>
                        </div>
                        <div id="subnav-veranstaltungen" class="absolute left-1/2 top-full z-50 -translate-x-1/2 pt-3" x-show="open" x-transition.opacity x-cloak>
                            <ul class="min-w-60 border-t-4 border-dpsg-rot bg-white py-2 text-left whitespace-nowrap shadow-xl">
                                <li><a href="{{ url('/veranstaltungen') }}" @class(['block px-4 py-2 text-xs uppercase tracking-wide text-text-secondary hover:text-dpsg-rot', 'text-dpsg-rot' => $eventsCurrent])>Übersicht {{ \App\Support\Site::termineLabel() }}</a></li>
                                @if (\App\Support\Site::kalenderUnterTerminen())
                                    <li><a href="{{ url('/kalender') }}" @class(['flex items-center gap-2 px-4 py-2 hover:bg-surface-muted hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('kalender'), 'font-normal' => ! request()->is('kalender')])>Kalender</a></li>
                                @endif
                                @if (\App\Support\Ausbau::anmeldungen())
                                    <li><a href="{{ url('/meine-anmeldungen') }}" @class(['flex items-center gap-2 px-4 py-2 hover:bg-surface-muted hover:text-dpsg-rot', 'text-dpsg-rot' => $mineActive, 'font-normal' => ! $mineActive]) @if ($mineActive) aria-current="page" @endif>Meine Anmeldungen</a></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                    @unless (\App\Support\Site::kalenderUnterTerminen())
                        <li class="flex-1 border-l border-dpsg-mittelbeige text-center">
                            <a href="{{ url('/kalender') }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('kalender')])>Kalender</a>
                        </li>
                    @endunless
                    <li class="flex-1 border-l border-dpsg-mittelbeige text-center">
                        <a href="{{ url('/aktuelles') }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('aktuelles*')])>Aktuelles</a>
                    </li>
                @else
                    {{-- Ohne Termine (Ausbaustufe 1–2): Kontakt im Menü, damit die Leiste nicht leer ist. --}}
                    <li class="flex-1 border-l border-dpsg-mittelbeige text-center first:border-l-0">
                        <a href="{{ url('/kontakt') }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => request()->is('kontakt')])>Kontakt</a>
                    </li>
                @endif
            </ul>
        </nav>
    </div>

    <main id="inhalt" class="flex-1 @yield('main_class')">
        @yield('content')
    </main>

    {{-- Schlanker Footer (Rückmeldung Talia): kein zweites Menü, nur
         Kontakt, Service und der Newsletter. Anpassbar unter Verwaltung →
         Fußzeile (App\Support\Fusszeile), sonst Angaben der Instanz. --}}
    @php($fuss = \App\Support\Fusszeile::anzeigen())
    @php($contact = $fuss['kontakt'])
    {{-- isolate + overflow-x-clip: die Wiese der Zelte läuft hinter allen Spalten bis an den Rand. --}}
    <footer class="isolate mt-20 overflow-x-clip bg-dpsg-hellbeige text-text">
        <div @class(['mx-auto grid max-w-6xl gap-10 px-4 pt-12 text-sm md:grid-cols-3', 'pb-12' => $fuss['spalte3'] !== 'zelte', 'pb-12 md:pb-24' => $fuss['spalte3'] === 'zelte'])>
            <div>
                <h2 class="mb-4 font-serif text-base font-bold">{{ $contact['name'] }}</h2>
                <p class="font-bold">{{ $contact['office'] }}</p>
                <p>
                    @if (filled($contact['street'] ?? null)){{ $contact['street'] }}<br />@endif
                    {{ $contact['city'] }}
                </p>
                <ul class="mt-3 space-y-2">
                    @if (filled($contact['phone'] ?? null))
                        <li class="flex items-center gap-2"><x-heroicon-o-phone class="h-4 w-4 shrink-0" aria-hidden="true" /><a href="tel:{{ $contact['phone_href'] }}" class="hover:underline">{{ $contact['phone'] }}</a></li>
                    @endif
                    <li class="flex items-center gap-2"><x-heroicon-o-envelope class="h-4 w-4 shrink-0" aria-hidden="true" /><a href="{{ \App\Support\Mailformular::href($contact['email']) }}" class="hover:underline">{{ $contact['email'] }}</a></li>
                </ul>
            </div>
            <div>
                <h2 class="mb-4 font-serif text-base font-bold">Service</h2>
                <ul class="space-y-2">
                    <li><a href="{{ url('/kontakt') }}" class="hover:underline">Kontakt</a></li>
                    @if (\App\Support\Ausbau::gruppen())
                        <li><a href="{{ \App\Support\Site::officeUrl() }}" class="hover:underline">{{ \App\Support\Site::officeLabel() }}</a></li>
                    @endif
                    @if (\App\Support\Ausbau::anmeldungen())
                        <li><a href="{{ url('/meine-anmeldungen') }}" class="hover:underline">Meine Anmeldungen</a></li>
                    @endif
                    <li>
                        <button type="button" x-data @click="$dispatch('open-consent-settings')" class="hover:underline">Datenschutz-Einstellungen</button>
                    </li>
                    <li><a href="{{ url('/admin') }}" class="hover:underline">Redaktions-Login</a></li>
                    <li><a href="{{ url('/status') }}" class="hover:underline">Systemstatus</a></li>
                    @foreach ($fuss['service'] as $link)
                        <li><a href="{{ str_starts_with($link['url'], '/') ? url($link['url']) : $link['url'] }}" @unless (str_starts_with($link['url'], '/')) target="_blank" rel="noopener" @endunless class="hover:underline">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
                {{-- Mit Newsletter steht „DPSG im Netz“ hier, sonst oben in der dritten Spalte. --}}
                @if ($fuss['spalte3'] === 'newsletter')
                    @include('partials.footer-netz', ['class' => 'mb-3 mt-6', 'links' => $fuss['netz']])
                @endif
            </div>
            @if ($fuss['spalte3'] === 'newsletter')
                <div>
                    <a href="{{ route('newsletter.index') }}" class="group block overflow-hidden bg-white shadow-sm hover:shadow-md">
                        <span class="block bg-[#eff2f7] px-4 py-3">
                            @include('newsletter.logo', ['class' => 'h-auto w-full', 'lazy' => true])
                        </span>
                        <span class="block p-4">
                            Termine und Neuigkeiten aus dem {{ config('dpsg.site.level') }} direkt ins Postfach.
                            <span class="mt-2 block font-bold text-dpsg-rot group-hover:underline">{{ config('dpsg.newsletter.name') }} abonnieren →</span>
                        </span>
                    </a>
                </div>
            @else
                {{-- Ohne Newsletter (Stämme, Bezirke): oben „DPSG im Netz“, darunter Zelte auf der Wiese
                     bzw. ein eigenes Bild, die auf dem blauen Balken stehen. Bild erst ab md (eigene Spalte);
                     auf dem Handy wäre es nur Deko unter dem Text. --}}
                <div class="flex flex-col">
                    @include('partials.footer-netz', ['class' => 'mb-4', 'links' => $fuss['netz']])
                    @if ($fuss['spalte3'] === 'zelte')
                        {{-- Die Wiese des Bildes (untere 55 %) läuft als Band über die ganze Breite weiter;
                             es hängt am Bild, damit der Horizont bei jeder Bildgröße passt. --}}
                        <div class="mt-auto hidden items-end justify-end pt-6 md:-mb-24 md:flex" aria-hidden="true">
                            <div class="relative w-full max-w-[300px]">
                                <span class="absolute -left-[200vw] -right-[200vw] bottom-0 -z-10 h-[55%] bg-[linear-gradient(to_bottom,rgba(101,146,8,0),#b7cc8b_3px,#659208_9px)]"></span>
                                <picture class="relative block">
                                    <source type="image/webp" srcset="{{ asset('images/footer/zelte.webp') }}" />
                                    <img src="{{ asset('images/footer/zelte.png') }}" alt="" width="544" height="288" loading="lazy" class="h-auto w-full [mask-image:linear-gradient(to_right,transparent,#000_15%)]" />
                                </picture>
                            </div>
                        </div>
                    @elseif ($fuss['spalte3'] === 'bild' && $fuss['bild'])
                        <div class="-mb-12 mt-auto hidden items-end justify-end pt-6 md:flex">
                            @if ($fuss['bild_link'])
                                <a href="{{ str_starts_with($fuss['bild_link'], '/') ? url($fuss['bild_link']) : $fuss['bild_link'] }}" @unless (str_starts_with($fuss['bild_link'], '/')) target="_blank" rel="noopener" @endunless class="block w-full max-w-[300px]">
                                    <x-picture :media="$fuss['bild']" sizes="300px" loading="lazy" class="h-auto w-full" />
                                </a>
                            @else
                                <x-picture :media="$fuss['bild']" sizes="300px" loading="lazy" class="block h-auto w-full max-w-[300px]" />
                            @endif
                        </div>
                    @endif
                </div>
            @endif
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
