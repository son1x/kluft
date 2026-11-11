<x-mail::message>
Hallo {{ $name }},

@if ($gesperrt)
du hast dich seit über einem Jahr nicht im Backend von {{ config('dpsg.site.name') }} angemeldet. Damit niemand ungenutzte Zugänge missbrauchen kann, haben wir deinen Zugang gesperrt. Deine Beiträge und Seiten bleiben erhalten.

Brauchst du den Zugang wieder? Dann melde dich bei uns; wir schalten ihn gern frei.
@else
du hast dich lange nicht im Backend von {{ config('dpsg.site.name') }} angemeldet. Damit niemand ungenutzte Zugänge missbrauchen kann, sperren wir deinen Zugang am **{{ $datum }}**.

Du brauchst ihn noch? Dann melde dich einfach vorher einmal an – mehr ist nicht nötig.

<x-mail::button :url="url('/admin')">
Jetzt anmelden
</x-mail::button>
@endif

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
