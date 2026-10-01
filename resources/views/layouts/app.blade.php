<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('seo_title', $title ?? 'DPSG Diözesanverband Trier')</title>
    @hasSection('seo_description')
        <meta name="description" content="@yield('seo_description')" />
    @endif
    <link rel="icon" href="{{ asset('favicon.ico') }}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Strukturprinzip nach bistum-trier.de (Pflichtenheft 7.1): schmale
     Servicezeile mit hängendem Logo-Badge, Hero bzw. Kopfband, darüber
     schwebende Hauptnavigation, mehrspaltiger Footer. Farben/Schriften
     ausschließlich DPSG-CD (7.2). --}}
<body class="min-h-screen flex flex-col bg-surface font-sans text-text" x-data="{ navOpen: false }">
    <a href="#inhalt" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:bg-white focus:px-4 focus:py-2 focus:text-dpsg-blau">Zum Inhalt springen</a>

    @php($isHome = request()->routeIs('home'))

    <header class="relative z-30 bg-dpsg-blau text-white">
        <div class="relative mx-auto flex h-12 max-w-6xl items-center justify-end gap-6 px-4">
            <a href="{{ url('/') }}" class="absolute left-4 top-0 rounded-b-lg bg-white px-3 pb-3 pt-2 shadow-lg">
                <img src="{{ asset('images/logo-dv-trier.png') }}" alt="DPSG Diözesanverband Trier – zur Startseite" class="h-14 w-auto md:h-20" width="300" height="223" />
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
                    <li><a href="{{ url('/'.$navPage->slug) }}" @class(['block px-4 py-3', 'text-dpsg-rot' => request()->is($navPage->slug)])>{{ $navPage->title }}</a></li>
                @endforeach
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

    <div class="relative z-20 mx-auto hidden w-full max-w-6xl px-4 md:-mt-7 md:block">
        <nav aria-label="Hauptnavigation" class="bg-white shadow-lg">
            <ul class="flex items-center py-3 text-sm font-bold">
                <li class="flex-1 text-center">
                    <a href="{{ url('/') }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => $isHome]) @if ($isHome) aria-current="page" @endif>Start</a>
                </li>
                @foreach ($navPages ?? [] as $navPage)
                    @php($active = request()->is($navPage->slug))
                    <li class="flex-1 border-l border-dpsg-mittelbeige text-center">
                        <a href="{{ url('/'.$navPage->slug) }}" @class(['block px-3 py-1 hover:text-dpsg-rot', 'text-dpsg-rot' => $active]) @if ($active) aria-current="page" @endif>{{ $navPage->title }}</a>
                    </li>
                @endforeach
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
                    @endforeach
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
                <span class="sm:ml-auto">Stufen-Maskottchen: DPSG, Quelle dpsg.de</span>
            </div>
        </div>
    </footer>

    @include('partials.consent-banner')
</body>
</html>
