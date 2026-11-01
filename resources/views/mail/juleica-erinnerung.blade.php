<x-mail::message>
Hallo{{ $anVerwaltung ? '' : ' '.$name }},

@if ($anVerwaltung)
die Juleica von **{{ $name }}** {{ $abgelaufen ? 'ist am '.$bis.' abgelaufen' : 'läuft am '.$bis.' ab' }}. Für sie oder ihn ist keine E-Mail-Adresse hinterlegt, deshalb geht der Hinweis an euch.
@else
deine Juleica {{ $abgelaufen ? 'ist am '.$bis.' abgelaufen' : 'läuft am '.$bis.' ab' }}.
@endif

Verlängern lässt sie sich mit einer Fortbildung (in der Regel 8 Stunden, dazu ein gültiger Erste-Hilfe-Nachweis) und einem Antrag auf juleica.de. Mit gültiger Juleica gibt es z. B. beim Kirchlichen Jugendplan des Bistums Trier einen höheren Tagessatz für Freizeiten.

Nach der Verlängerung bitte das neue Ablaufdatum im Backend bei der Person eintragen (Personen → „Für Zuschussanträge“).

Gut Pfad<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
