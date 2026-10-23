<x-mail::message>
Hallo,

heute ist das Bestelldatum der Sammelbestellung „{{ $runde->name }}“, und die Runde ist noch offen. Bitte im Backend schließen: Unbezahltes wird dabei storniert (außer gehaltene Bestellungen), danach steht die Sammelliste für das Rüsthaus bereit.

<x-mail::button :url="$url">
Bestellrunden öffnen
</x-mail::button>

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
