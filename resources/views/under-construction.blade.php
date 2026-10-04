<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" />
    <title>{{ config('dpsg.site.name') }} – neues CMS im Aufbau</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center bg-dpsg-blau px-4 py-16 text-white">
    <main class="max-w-xl w-full">
        <p class="font-sans text-sm uppercase tracking-wide text-dpsg-cyan mb-3">{{ config('dpsg.site.name') }}</p>
        <h1 class="font-serif text-3xl sm:text-4xl font-bold mb-4 leading-tight">
            Das neue CMS entsteht gerade hier.
        </h1>
        <p class="font-sans text-base text-white/85 mb-6 leading-relaxed">
            Diese Seite läuft bereits auf dem neuen Custom-CMS
            (Laravel&nbsp;+&nbsp;Filament), das die bisherige WordPress-Seite
            schrittweise ersetzt. Redaktionelle Inhalte, Anmeldungen und
            Kalender folgen in den nächsten Ausbaustufen – siehe
            <code class="bg-white/10 rounded px-1.5 py-0.5 text-sm">docs/pflichtenheft.md</code>
            im Repository.
        </p>
        <ul class="font-sans text-sm text-white/70 space-y-1.5 border-t border-white/20 pt-4">
            <li>✓ Entra-ID-/M365-Login für die Redaktion</li>
            <li>✓ Notfall-Zugang mit TOTP</li>
            <li>✓ Rollen &amp; Rechte je Stufe/Sektion</li>
            <li>… Seiten, Blog, Veranstaltungen, Kalender, Georgsbote</li>
        </ul>
    </main>
</body>
</html>
