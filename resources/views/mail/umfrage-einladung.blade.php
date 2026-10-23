<x-mail::message>
Hallo {{ $name }},

@if ($veranstaltung)
du warst bei „{{ $veranstaltung }}“ angemeldet. Wir haben dazu eine kurze Umfrage: **{{ $umfrage->titel }}**.
@else
wir haben eine kurze Umfrage für dich: **{{ $umfrage->titel }}**.
@endif

@if ($umfrage->endet_am)
Sie ist bis {{ $umfrage->endet_am->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm') }} Uhr geöffnet.
@endif
@if ($umfrage->anonym)
Deine Antworten sind anonym: Wir sehen nur, dass du teilgenommen hast, nicht was.
@endif

<x-mail::button :url="$url">
Zur Umfrage
</x-mail::button>

Der Link gilt nur für dich, bitte nicht weitergeben.

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
