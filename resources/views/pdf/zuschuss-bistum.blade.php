{{-- Bistum Trier: „Antrag auf Zuschuss nach dem kirchlichen Jugendplan“
     (Formular 2025) mit Programmfolge, Nachbau ohne das Logo des Bistums. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Antrag KJP</title>@include('pdf.partials.zuschuss-stil')</head>
<body>
@php
    $x = fn (bool $an) => '<span class="box">'.($an ? 'X' : '&nbsp;').'</span>';
    $euro = fn (?int $c) => $c === null ? '' : number_format($c / 100, 2, ',', '.').' €';
    $b = (array) $event->zuschuss_bericht;
    $f = $finanzen;
    $ende = $event->ends_at ?? $event->starts_at;
    $g = $zaehlung['geschlecht']['tn'];
    $juleica = $betreuung->filter(fn ($p) => $p['juleica'])->count();
    $ausgabenUv = ($f['unterkunft'] ?? 0) + ($f['verpflegung'] ?? 0);
    $sonstigeAusgaben = ($f['raummiete'] ?? 0) + ($f['material'] ?? 0) + ($f['durchfuehrung'] ?? 0);
    $einnahmen = $andereZuschuesse + ($f['teilnahmebeitraege'] ?? 0) + ($f['spenden'] ?? 0) + ($f['sonstige'] ?? 0);
@endphp
<h1>Antrag auf Zuschuss nach dem kirchlichen Jugendplan</h1>
<p class="unter">an das Bischöfliche Generalvikariat Trier, Abteilung Jugend · Antrag spätestens 8 Wochen nach Ende der Maßnahme</p>
<table style="width:100%"><tr><td style="width:50%; vertical-align:top; padding-right:3mm">
    <table class="f">
        <tr><th class="kopf" colspan="2">Antragsteller (Träger der Maßnahme)</th></tr>
        <tr><td class="l">Institution</td><td>{{ $as['name'] }}</td></tr>
        <tr><td class="l">Name | Vorname</td><td>{{ $as['rueckfragen'] }}</td></tr>
        <tr><td class="l">Straße | PLZ | Ort</td><td>{{ $as['anschrift'] }}</td></tr>
        <tr><td class="l">E-Mail</td><td>{{ $as['email'] }}</td></tr>
        <tr><td class="l">Telefon</td><td>{{ $as['telefon'] }}</td></tr>
    </table>
    <table class="f">
        <tr><th class="kopf" colspan="2">Bankverbindung</th></tr>
        <tr><td class="l">IBAN</td><td>{{ $as['iban'] }}</td></tr>
        <tr><td class="l">BIC</td><td>{{ $as['bic'] }}</td></tr>
        <tr><td class="l">Konto-Inhaber</td><td>{{ $as['inhaber'] }}</td></tr>
    </table>
</td><td style="width:50%; vertical-align:top">
    <table class="f">
        <tr><th class="kopf">Art der Maßnahme</th></tr>
        <tr><td>
            {!! $x(false) !!} 1. Religiöse Bildung und Gemeindekatechese<br>
            {!! $x(false) !!} 2. Schul- und Jugendpastoral<br>
            {!! $x($event->zuschuss_art === 'schulung') !!} 3. Qualifizierung und Schulung von Ehrenamtlichen<br>
            {!! $x(in_array($event->zuschuss_art, ['freizeit', 'bildung'], true)) !!} 4. Soziale Bildung und Freizeiten<br>
            {!! $x(false) !!} 5. Innovative oder modellhafte Maßnahmen</td></tr>
    </table>
    <table class="f">
        <tr><td class="l">Ort der Durchführung</td><td colspan="3">{{ $event->location }}</td></tr>
        <tr><td class="l">Datum</td><td colspan="3">vom {{ $event->starts_at->format('d.m.Y') }} bis {{ $ende->format('d.m.Y') }}</td></tr>
        <tr><td class="l">Uhrzeit</td><td colspan="3">von {{ $event->starts_at->format('H:i') }} bis {{ $ende->format('H:i') }}</td></tr>
        <tr><td class="l"><strong>Teilnehmende gesamt</strong></td><td colspan="3"><strong>{{ $zaehlung['gesamt']['tn'] }}</strong>, davon {{ $g['m'] }} männlich, {{ $g['w'] }} weiblich und {{ $g['d'] }} divers</td></tr>
        <tr><td class="l">Betreuungskräfte</td><td colspan="3">{{ $zaehlung['gesamt']['betreuung'] }}, davon mit gültigem Juleica-Nachweis: {{ $juleica }}</td></tr>
    </table>
</td></tr></table>

<table style="width:100%"><tr><td style="width:50%; vertical-align:top; padding-right:3mm">
    <table class="f">
        <tr><th class="kopf">Ausgaben</th><th class="kopf z">Betrag</th></tr>
        <tr><td>Unterkunft und Verpflegung</td><td class="z">{{ $euro($ausgabenUv ?: null) }}</td></tr>
        <tr><td>Fahrtkosten</td><td class="z">{{ $euro($f['fahrt'] ?? null) }}</td></tr>
        <tr><td>Referentenkosten (Honorar, Fahrtkosten)</td><td class="z">{{ $euro($f['honorare'] ?? null) }}</td></tr>
        <tr><td>Sonstige Kosten (Miete, Arbeitsmaterial u. ä.)</td><td class="z">{{ $euro($sonstigeAusgaben ?: null) }}</td></tr>
        <tr><td><strong>Summe der Ausgaben</strong></td><td class="z"><strong>{{ $euro($f['ausgaben']) }}</strong></td></tr>
    </table>
</td><td style="width:50%; vertical-align:top">
    <table class="f">
        <tr><th class="kopf">Einnahmen</th><th class="kopf z">Betrag</th></tr>
        <tr><td>zu erwartende Zuschüsse (nicht Zuschuss des Bistums)</td><td class="z">{{ $euro($andereZuschuesse ?: null) }}</td></tr>
        <tr><td>Teilnahmebeiträge</td><td class="z">{{ $euro($f['teilnahmebeitraege'] ?? null) }}</td></tr>
        <tr><td>Sonstige Einnahmen</td><td class="z">{{ $euro((($f['spenden'] ?? 0) + ($f['sonstige'] ?? 0)) ?: null) }}</td></tr>
        <tr><td><strong>Summe der Einnahmen</strong></td><td class="z"><strong>{{ $euro($einnahmen) }}</strong></td></tr>
        <tr><td><strong>Ungedeckte Kosten</strong></td><td class="z"><strong>{{ $euro(max(0, $f['ausgaben'] - $einnahmen)) }}</strong></td></tr>
    </table>
</td></tr></table>
<p class="klein">„Zu erwartende Zuschüsse“: Schätzung von Kluft für {{ implode(', ', $andereGeber) ?: 'keine weiteren Stellen' }}; bitte nach Bewilligung anpassen.</p>
<p class="hinweis"><strong>Bestätigung:</strong> 1. Die Maßnahme wurde in der angegebenen Zeit mit beigefügtem Programm und mit den aufgeführten teilnehmenden Personen durchgeführt. 2. Der Antragsteller erkennt die Richtlinien vom 01.01.2023 zum Kirchlichen Jugendplan an. 3. Der beantragte Zuschuss wird ausschließlich für die angegebene Maßnahme verwendet. Die Auszahlung erfolgt nur bis zur Höhe des Defizits.</p>
<table class="sig"><tr><td><div class="klein">Für die Richtigkeit der Angaben</div><br><div class="linie">Ort | Datum</div><br><br><div class="linie">Unterschrift Leiter*in der Maßnahme</div></td>
    <td><div class="klein">Bestätigung bei auswärtiger Unterbringung</div><br><div class="linie">Ort | Datum</div><br><br><div class="linie">Unterschrift und Stempel Hausleitung</div></td></tr></table>

<div class="neu"></div>
<h1>Programmfolge zum Antrag</h1>
<p>Schwerpunktthema: <strong>{{ $b['thema'] ?? $event->title }}</strong></p>
<table class="f">
    <tr><th class="kopf" style="width:38mm">Datum/Uhrzeit von bis</th><th class="kopf">Thema</th><th class="kopf" style="width:40mm">Referent*in</th></tr>
    @forelse ($programm as $punkt)
        <tr><td>{{ $punkt['zeit'] }}</td><td>{{ $punkt['thema'] }}</td><td>{{ $punkt['referent'] }}</td></tr>
    @empty
        @for ($i = 0; $i < 9; $i++)<tr><td style="height:14mm"></td><td></td><td></td></tr>@endfor
    @endforelse
</table>
<p class="klein">Liste der Teilnehmenden und der Betreuungskräfte: siehe beigefügte Teilnahmeliste (Original-Unterschriften).</p>
</body></html>
