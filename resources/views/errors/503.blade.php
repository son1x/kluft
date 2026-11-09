<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>Kurz in Wartung – {{ config('dpsg.site.name') }}</title>
    {{-- Bewusst ohne externe/gebaute Assets: wird beim Deploy vorab gerendert
         und ausgeliefert, während die übrigen Dateien ausgetauscht werden. --}}
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #003056; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #1c1f26; }
        main { background: #fff; max-width: 28rem; margin: 1rem; padding: 2rem; border-radius: .5rem; box-shadow: 0 10px 30px rgba(0, 0, 0, .25); }
        h1 { margin: 0 0 .75rem; font-size: 1.5rem; color: #003056; }
        p { margin: 0; line-height: 1.5; }
    </style>
</head>
<body>
    <main>
        <h1>Kurz in Wartung</h1>
        {{-- Wartungsmodus von Hand (App\Http\Middleware\Wartungsmodus) mit eigenem Text; sonst Deploy/Update. --}}
        @isset($wartungText)
            <p style="white-space: pre-line">{{ $wartungText }}</p>
            @if (($wartungBis ?? null)?->isFuture())
                <p style="margin-top: .75rem">Voraussichtlich wieder da: {{ $wartungBis->locale('de')->timezone(config('app.timezone'))->isoFormat('dd, D. MMMM, HH:mm') }} Uhr.</p>
            @endif
        @else
            <p>Wir spielen gerade eine neue Version der Website ein. In ein, zwei Minuten geht es weiter – bitte lade die Seite dann neu.</p>
        @endisset
        <p style="margin-top: 1rem; font-size: .875rem; color: #5c6670;">{{ config('dpsg.site.name') }}</p>
    </main>
</body>
</html>
