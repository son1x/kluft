<x-mail::message>
@if ($art === 'pruefen')
Hallo,

in der Sammelbestellung „{{ $bestellung->runde->name }}“ hat {{ $bestellung->name }} Artikel per Rüsthaus-Nummer bestellt. Bitte Artikel, Größe und Preis prüfen und bestätigen; danach geht die Zahlungsaufforderung raus.

<x-mail::button :url="\App\Filament\Resources\Shop\BestellungResource::getUrl('edit', ['record' => $bestellung])">
Bestellung prüfen
</x-mail::button>
@else
Hallo {{ $bestellung->name }},

@if ($art === 'doi')
danke für deine Bestellung in der Sammelbestellung „{{ $bestellung->runde->name }}“. Bitte bestätige sie über den Knopf – erst dann gilt sie.

<x-mail::button :url="$link">
Bestellung bestätigen
</x-mail::button>

Hast du nichts bestellt, kannst du diese Mail einfach löschen; unbestätigte Bestellungen verfallen.
@elseif ($art === 'storno')
deine Bestellung in der Sammelbestellung „{{ $bestellung->runde->name }}“ ist storniert.@if ($hinweis) {{ $hinweis }}@endif

Hast du schon etwas bezahlt, bekommst du es zurück; die Kasse meldet sich.
@elseif ($art === 'abholbereit')
die Ware aus der Sammelbestellung „{{ $bestellung->runde->name }}“ ist da.@if ($hinweis) {{ $hinweis }}@endif

Bei der Ausgabe unterschreibst du bzw. das Mitglied den Empfang.
@else
deine Bestellung ist eingegangen. Bestellt wird beim Rüsthaus am **{{ $bestellung->runde->bestelldatum->format('d.m.Y') }}** – bis dahin muss sie bezahlt sein. Die Zahlungsaufforderung bekommst du in einer eigenen Mail@if ($bestellung->positionen->contains('bestaetigt', false)), für frei eingegebene Artikel erst, wenn wir sie geprüft haben@endif.
@endif

<x-mail::table>
| Für | Artikel | Anzahl | Preis |
|:--|:--|:-:|--:|
@foreach ($bestellung->positionen as $p)
| {{ $p->mitglied }} | {{ $p->bezeichnung }}@if ($p->groesse) ({{ $p->groesse }})@endif | {{ $p->menge }} | {{ $p->preis_cent ? \App\Support\Sammelbestellung::euro($p->summeCent()) : 'wird geprüft' }}@if (! $p->bestaetigt && $p->preis_cent) (vorläufig)@endif |
@endforeach
</x-mail::table>

@if ($art === 'bestaetigt' && $link)
Stand und Storno (solange die Runde offen und nichts bezahlt ist): [deine Bestellung]({{ $link }})
@endif
@endif

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
