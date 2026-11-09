{{-- Nur das Anmeldeformular zum Einbinden als iframe (EventController::embed).
     Ohne Kopf, Navigation, Fußzeile und Testsystem-Band; Links auf die
     Website öffnen ein neues Fenster. --}}
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Anmeldung: {{ $event->title }} – {{ config('dpsg.site.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($stufenfarben = \App\Support\Site::stufenfarbenCss())
        <style>{!! $stufenfarben !!}</style>
    @endif
</head>
<body class="bg-surface font-sans text-text" data-einbetten>
    <main class="mx-auto max-w-3xl px-4 py-6">
        <p class="font-serif text-xl font-bold">{{ $event->title }}</p>
        <p class="mt-1 text-sm text-text-secondary">
            {{ $event->dateLabel() }}
            @if ($event->reg_closes_at) · Anmeldung bis {{ $event->reg_closes_at->locale('de')->isoFormat('D. MMMM YYYY') }} @endif
            · <a href="{{ route('events.show', $event->slug) }}" target="_blank" rel="noopener" class="text-dpsg-rot underline">Alle Infos</a>
        </p>
        <div class="mt-6">@include('events.partials.anmeldung')</div>
        <p class="mt-6 text-xs text-text-secondary">
            {{ config('dpsg.site.name') }} ·
            <a href="{{ url('/datenschutz') }}" target="_blank" rel="noopener" class="underline">Datenschutz</a> ·
            <a href="{{ url('/impressum') }}" target="_blank" rel="noopener" class="underline">Impressum</a>
        </p>
    </main>
</body>
</html>
