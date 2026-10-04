<x-mail::message>
Hallo,

dein Code für „Meine Anmeldungen“ beim {{ config('dpsg.site.name') }}:

<x-mail::panel>
<span style="font-size: 28px; font-weight: bold; letter-spacing: 6px;">{{ $code }}</span>
</x-mail::panel>

Gib ihn auf der Seite ein, auf der du ihn angefordert hast. Er gilt {{ $minutes }} Minuten. Falls du die Seite geschlossen hast: [{{ $url }}]({{ $url }})

Wenn du keinen Code angefordert hast, kannst du diese E-Mail einfach ignorieren – ohne den Code kommt niemand an deine Anmeldungen.

Gut Pfad!<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
