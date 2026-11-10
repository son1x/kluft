@php($r = $reservierung)
@php($m = $r->material)
<x-mail::message>
@if ($art === 'anfrage')
Hallo,

über die Website kam eine Anfrage:

- **{{ $m->name }}**{{ $r->menge > 1 ? ', '.$r->menge.' Stück' : '' }}
- {{ $r->zeitraum() }}
- {{ $r->name }}{{ $r->gruppierung ? ' ('.$r->gruppierung.')' : '' }}, {{ $r->email }}{{ $r->telefon ? ', '.$r->telefon : '' }}
@if ($r->zweck)
- Wofür: {{ $r->zweck }}
@endif

Im Zeitraum sind derzeit {{ $m->frei($r->von, $r->bis) }} von {{ $m->menge }} frei.

<x-mail::button :url="\App\Filament\Resources\Material\ReservierungResource::getUrl('index', panel: 'admin')">Anfrage ansehen</x-mail::button>
@else
Hallo {{ $r->name }},

@if ($art === 'zugesagt')
gute Nachricht: **{{ $m->name }}**{{ $r->menge > 1 ? ' ('.$r->menge.' Stück)' : '' }} ist für dich reserviert, {{ $r->zeitraum() }}.

@if ($m->ort)
Abholung: {{ $m->ort }}. Den genauen Termin für Abholung und Rückgabe sprecht bitte direkt ab.
@else
Den Termin für Abholung und Rückgabe sprecht bitte direkt ab.
@endif
Bitte bring alles vollständig und sauber zurück und sag Bescheid, wenn etwas kaputtgegangen ist.
@elseif ($art === 'abgelehnt')
leider können wir dir **{{ $m->name }}** für {{ $r->zeitraum() }} nicht verleihen.
@if ($grund)

{{ $grund }}
@endif
@else
kurze Erinnerung: **{{ $m->name }}** ist bis morgen, {{ $r->bis->format('d.m.Y') }}, ausgeliehen. Bitte denk an die Rückgabe, vollständig und sauber.
@endif
@endif

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
