<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('seo_title', $title ?? 'DPSG Diözesanverband Trier')</title>
    @hasSection('seo_description')
        <meta name="description" content="@yield('seo_description')" />
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col text-text bg-surface">
    <header class="bg-dpsg-blau text-white">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="font-serif text-lg font-bold">DPSG Diözesanverband Trier</a>
            <nav aria-label="Hauptnavigation">
                <ul class="flex flex-wrap gap-4 text-sm">
                    @foreach ($navPages ?? [] as $navPage)
                        <li>
                            <a href="{{ url('/'.$navPage->slug) }}" class="hover:underline">{{ $navPage->title }}</a>
                        </li>
                    @endforeach
                    <li><a href="{{ url('/aktuelles') }}" class="hover:underline">Aktuelles</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="bg-dpsg-hellbeige text-text-secondary mt-auto">
        <div class="max-w-5xl mx-auto px-4 py-6 text-sm flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ url('/impressum') }}" class="hover:underline">Impressum</a>
            <a href="{{ url('/datenschutz') }}" class="hover:underline">Datenschutz</a>
            <a href="{{ url('/kontakt') }}" class="hover:underline">Kontakt</a>
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-consent-settings'))" class="hover:underline">
                Datenschutz-Einstellungen
            </button>
        </div>
    </footer>

    @include('partials.consent-banner')
</body>
</html>
