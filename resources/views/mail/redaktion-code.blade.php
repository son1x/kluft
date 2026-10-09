<x-mail::message>
Hallo,

dein Code für die Anmeldung im Redaktionsbereich von {{ config('dpsg.site.name') }}@if ($rolle) – Zugang „{{ $rolle }}“@endif:

<x-mail::panel>
<span style="font-size: 28px; font-weight: bold; letter-spacing: 6px;">{{ $code }}</span>
</x-mail::panel>

Gib ihn auf der Seite ein, auf der du ihn angefordert hast. Er gilt {{ $minutes }} Minuten. Falls du die Seite geschlossen hast: [{{ $url }}]({{ $url }})

@if ($rolle)
Diesen Code bekommt jede Person, die Zugriff auf dieses Postfach hat. Gib ihn nicht weiter.

@endif
Wenn du keinen Code angefordert hast, kannst du diese E-Mail ignorieren – ohne den Code kommt niemand hinein.

Gut Pfad!<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
