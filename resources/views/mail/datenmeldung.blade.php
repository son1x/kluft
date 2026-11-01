<x-mail::message>
Hallo,

danke, dass ihr die Daten von **{{ $mitglied }}** aktualisiert habt. Bitte bestätige sie über den Knopf – erst dann übernehmen wir sie in unsere Prüfliste.

<x-mail::button :url="$link">
Daten bestätigen
</x-mail::button>

Hast du das Formular nicht ausgefüllt, lösche diese Mail einfach; ohne Bestätigung werden die Angaben nach 48 Stunden gelöscht.

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
