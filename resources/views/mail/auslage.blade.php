@php($a = $auslage)
@php($betrag = \App\Support\Zahlungen::euro($a->summe_cent))
<x-mail::message>
@if ($art === 'eingereicht')
Hallo,

**{{ $a->name }}** hat eine Auslage eingereicht:

- {{ $a->anlass }} ({{ $a->beschreibung() }}) vom {{ $a->datum->format('d.m.Y') }}
- Betrag: **{{ $betrag }}**{{ count($a->belege ?? []) > 0 ? ', '.count($a->belege).' Beleg(e)' : '' }}

<x-mail::button :url="\App\Filament\Resources\Auslagen\AuslageResource::getUrl('view', ['record' => $a], panel: 'admin')">Prüfen</x-mail::button>
@else
Hallo {{ $a->name }},

@if ($art === 'freigegeben')
deine Auslage „{{ $a->anlass }}“ vom {{ $a->datum->format('d.m.Y') }} über **{{ $betrag }}** ist freigegeben. Die Kasse überweist den Betrag mit der nächsten Sammelüberweisung.
@elseif ($art === 'abgelehnt')
deine Auslage „{{ $a->anlass }}“ vom {{ $a->datum->format('d.m.Y') }} über {{ $betrag }} wurde nicht erstattet.

**Grund:** {{ $a->grund }}

Bei Fragen antworte der Kasse gern direkt.
@else
die Erstattung deiner Auslage „{{ $a->anlass }}“ über **{{ $betrag }}** ist am {{ $a->ausgezahlt_am?->format('d.m.Y') }} an dich überwiesen worden.
@endif

Danke für deinen Einsatz!
@endif

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
