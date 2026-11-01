<x-mail::message>
Hallo,

für **{{ $veranstaltung }}** endet {{ $tage > 0 ? 'in '.$tage.' Tag'.($tage === 1 ? '' : 'en') : 'heute' }} die Frist für den Zuschussantrag beim **{{ $geber }}** ({{ $frist }}). Bisher ist im Backend nichts als eingereicht vermerkt.

<x-mail::button :url="$link">
Zuschussantrag öffnen
</x-mail::button>

Dort lassen sich alle Unterlagen herunterladen. Nach dem Einreichen bitte „Eingereicht am“ eintragen, dann kommt keine weitere Erinnerung.

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
