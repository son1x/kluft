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
        </p>
        {{-- Infos zur Veranstaltung hier im Rahmen statt als Link auf die (vor dem Livegang noch nicht öffentliche) Website. --}}
        @if (filled($event->description) || $event->location || $event->contact_email)
            <details class="mt-3 bg-white p-4 shadow-sm">
                <summary class="cursor-pointer font-bold text-dpsg-blau">Mehr zur Aktion</summary>
                @if (filled($event->description))
                    <div class="prose mt-3 max-w-none text-sm">{!! str($event->description)->sanitizeHtml() !!}</div>
                @endif
                <dl class="mt-3 space-y-1 text-sm">
                    @if ($event->location)<div><dt class="inline font-bold">Wo:</dt> <dd class="inline">{{ $event->location }}</dd></div>@endif
                    @if ($event->contact_email)<div><dt class="inline font-bold">Kontakt:</dt> <dd class="inline">{{ $event->contact_name }} <a href="mailto:{{ $event->contact_email }}" class="text-dpsg-rot underline">{{ $event->contact_email }}</a></dd></div>@endif
                </dl>
            </details>
        @endif
        <div class="mt-6">@include('events.partials.anmeldung')</div>
        <p class="mt-6 text-xs text-text-secondary">
            {{ config('dpsg.site.name') }} ·
            <a href="{{ url('/datenschutz') }}" target="_blank" rel="noopener" class="underline">Datenschutz</a> ·
            <a href="{{ url('/impressum') }}" target="_blank" rel="noopener" class="underline">Impressum</a>
        </p>
    </main>
</body>
</html>
