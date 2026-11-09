{{-- Stand eines Backup-Auftrags (BackupStandController). Ohne Layout und ohne
     gebaute Assets: funktioniert auch, während die Website ersetzt wird. --}}
@php($fertig = in_array($stand['status'], ['fertig', 'fehler'], true))
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    @unless ($fertig)
        <meta http-equiv="refresh" content="5" />
    @endunless
    @php($titel = match ($stand['art']) { 'backup' => 'Backup erstellen', 'uebertragen' => 'Auf andere Website übertragen', default => 'Backup einspielen' })
    <title>{{ $titel }} – {{ config('dpsg.site.name') }}</title>
    <style>
        body { margin: 0; background: #f3efe6; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #1c1f26; }
        main { max-width: 44rem; margin: 2rem auto; padding: 1.5rem; background: #fff; border-radius: .5rem; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; color: #003056; }
        .status { font-weight: 700; margin: 0 0 1rem; }
        .fehler { color: #a50034; } .fertig { color: #1a7f37; }
        ol { margin: 0; padding-left: 1.25rem; line-height: 1.6; font-size: .95rem; }
        a { color: #003056; font-weight: 700; }
        p.leise { color: #5c6670; font-size: .85rem; }
    </style>
</head>
<body>
<main>
    <h1>{{ $titel }}</h1>
    <p @class(['status', 'fehler' => $stand['status'] === 'fehler', 'fertig' => $stand['status'] === 'fertig'])>
        {{ match ($stand['status']) { 'wartet' => 'Wartet auf den Cronjob (höchstens eine Minute) …', 'laeuft' => 'Läuft …', 'fertig' => 'Fertig.', default => 'Fehlgeschlagen.' } }}
    </p>
    <ol>
        @foreach ($stand['zeilen'] as $zeile)
            <li>{!! preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', e($zeile)) !!}</li>
        @endforeach
    </ol>
    @if ($fertig)
        <p><a href="{{ url('/admin') }}">Zum Backend</a>{{ $stand['art'] === 'einspielen' ? ' (bitte neu anmelden)' : '' }}</p>
    @else
        <p class="leise">Die Seite lädt sich alle 5 Sekunden neu. Sie kann geschlossen werden, der Auftrag läuft weiter.</p>
    @endif
</main>
</body>
</html>
