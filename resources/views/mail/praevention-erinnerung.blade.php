<x-mail::message>
Hallo{{ $uebersicht ? '' : ' '.$eintraege[0]['name'] }},

@if ($uebersicht)
diese Präventions-Nachweise laufen bald ab:

@foreach ($eintraege as $e)
- **{{ $e['name'] }}**: {{ $e['art'] }}{{ $e['bezeichnung'] ? ' ('.$e['bezeichnung'].')' : '' }}, gültig bis {{ $e['bis'] }} (noch {{ $e['tage'] }} Tage)
@endforeach

Die Personen mit E-Mail-Adresse haben selbst einen Hinweis bekommen.

<x-mail::button :url="url('/admin/praevention')">Zur Übersicht</x-mail::button>
@else
{{ $eintraege[0]['art'] === 'Führungszeugnis' ? 'dein erweitertes Führungszeugnis' : 'dein Nachweis „'.$eintraege[0]['art'].'“' }}{{ $eintraege[0]['bezeichnung'] ? ' ('.$eintraege[0]['bezeichnung'].')' : '' }} gilt noch bis **{{ $eintraege[0]['bis'] }}**.

Damit du ohne Unterbrechung mit Kindern und Jugendlichen arbeiten kannst, kümmere dich bitte rechtzeitig um die Erneuerung: Das Führungszeugnis beantragst du bei deiner Gemeinde (für Ehrenamtliche kostenlos, mit Bestätigung des Verbands), eine Präventionsschulung findest du im Programm des Bistums oder des Verbands.

Zeig den neuen Nachweis danach dem Vorstand bzw. dem Büro; gespeichert wird nur das Datum.
@endif

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
