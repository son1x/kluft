{{-- Kreisstadt Saarlouis: „Antrag und Verwendungsnachweis gem. Richtlinien zur
     Förderung der außerschulischen Jugendarbeit – Bezuschussung von
     Maßnahmen“ mit Teilnehmerliste, Nachbau. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Antrag Stadt Saarlouis</title>@include('pdf.partials.zuschuss-stil')</head>
<body>
@php
    $x = fn (bool $an) => '<span class="box">'.($an ? 'X' : '&nbsp;').'</span>';
    $euro = fn (?int $c) => $c === null ? '' : number_format($c / 100, 2, ',', '.').' €';
    $b = (array) $event->zuschuss_bericht;
    $tage = count($event->days());
    $ende = $event->ends_at ?? $event->starts_at;
    $art = $event->zuschuss_art;
    $betreuerUnter18 = $betreuung->filter(fn ($p) => $p['funktion'] !== 'R' && ($p['alter'] ?? 99) < 18)->count();
@endphp
<h1>Antrag und Verwendungsnachweis</h1>
<p class="unter">gem. „Richtlinien zur Förderung der außerschulischen Jugendarbeit“ der Kreisstadt Saarlouis – Bezuschussung von Maßnahmen –</p>
<table class="f">
    <tr><th class="kopf" colspan="4">I. Antragsteller</th></tr>
    <tr><td class="l">Name</td><td colspan="3"><strong>{{ $as['name'] }}</strong></td></tr>
    <tr><td class="l">Anschrift</td><td colspan="3">{{ $as['anschrift'] }}</td></tr>
    <tr><td class="l">Bankverbindung</td><td colspan="3">{{ $as['inhaber'] }}{{ $as['iban'] ? ', IBAN '.$as['iban'] : '' }}{{ $as['bic'] ? ', BIC '.$as['bic'] : '' }}</td></tr>
    <tr><td class="l">Auskunft erteilt</td><td>{{ $as['rueckfragen'] }}</td><td class="l">Telefon</td><td>{{ $as['telefon'] }}</td></tr>
</table>
<table class="f">
    <tr><th class="kopf" colspan="2">II. Förderungsbereich</th></tr>
    <tr><td>{!! $x($art === 'bildung') !!} Bildungsmaßnahme – § 5</td><td>{!! $x(false) !!} Jugendbegegnung besonderer Art – § 6</td></tr>
    <tr><td>{!! $x($art === 'freizeit') !!} Freizeitmaßnahme – § 7</td><td>{!! $x(false) !!} Jugendwerbung – § 8</td></tr>
</table>
<table class="f">
    <tr><th class="kopf" colspan="4">Die Maßnahme wurde wie folgt durchgeführt</th></tr>
    <tr><td class="l">a) Beginn</td><td>{{ $event->starts_at->format('d.m.Y H:i') }}</td><td class="l">Ende</td><td>{{ $ende->format('d.m.Y H:i') }}</td></tr>
    <tr><td class="l">b) Ort der Durchführung</td><td colspan="3">{{ $event->location }}</td></tr>
    <tr><td class="l">c) Art der Durchführung</td><td colspan="3">
        {!! $x($art === 'bildung' && $tage < 2) !!} Tages-/Abendseminar &nbsp; {!! $x($art === 'bildung' && $tage >= 2) !!} Wochenend-/Mehrtagesseminar &nbsp; {!! $x(false) !!} Tag der offenen Tür<br>
        {!! $x($art === 'freizeit' && $tage < 2) !!} Fahrt &nbsp; {!! $x($art === 'freizeit' && $tage >= 2) !!} Lager &nbsp; {!! $x(false) !!} Wanderung &nbsp; {!! $x(! in_array($art, ['freizeit', 'bildung'], true)) !!} sonstiges</td></tr>
    <tr><td class="l">d) Teilnehmerzahl</td><td>{{ $zaehlung['gesamt']['tn'] }}</td><td class="l">e) Zahl der Betreuer</td><td>{{ $zaehlung['gesamt']['betreuung'] }}, davon unter 18: {{ $betreuerUnter18 }}</td></tr>
</table>
<table class="f">
    <tr><th class="kopf">III. Beschreibung der Maßnahme</th></tr>
    <tr><td style="height: 16mm">{!! nl2br(e($b['methodik'] ?? $b['thema'] ?? '')) !!} {{ $programm !== [] ? '(Programm siehe Anlage)' : '' }}</td></tr>
    <tr><th class="kopf">IV. Sachbericht</th></tr>
    <tr><td style="height: 14mm">{!! filled($b['bericht'] ?? null) ? nl2br(e($b['bericht'])) : 'Die Maßnahme wurde programmgemäß durchgeführt.' !!}<br>Die Entfernung zwischen Saarlouis und dem angegebenen Ziel in km: <strong>{{ $b['entfernung_km'] ?? '' }}</strong></td></tr>
</table>
<table class="f">
    <tr><th class="kopf" colspan="3">V. Zahlenmäßiger Nachweis</th></tr>
    <tr><td colspan="2">Gesamtausgaben (Rechnungen als Anlage)</td><td class="z">{{ $euro($finanzen['ausgaben'] ?: null) }}</td></tr>
    <tr><th class="kopf">Bewilligte Zuschüsse Dritter: Bewilligende Stelle</th><th class="kopf">Datum und Aktenzeichen</th><th class="kopf z">€</th></tr>
    @foreach ($dritte as $d)<tr><td>{{ $d['name'] }}</td><td>{{ trim(($d['datum'] ?? '').' '.($d['aktenzeichen'] ?? '')) }}</td><td class="z">{{ $euro($d['betrag']) }}</td></tr>@endforeach
    @for ($i = count($dritte); $i < 3; $i++)<tr><td>&nbsp;</td><td></td><td></td></tr>@endfor
    <tr><td colspan="2" class="z">Bewilligter Gesamtbetrag</td><td class="z">{{ $dritte !== [] ? $euro(array_sum(array_column($dritte, 'betrag'))) : '' }}</td></tr>
</table>
<table class="f">
    <tr><th class="kopf">VI. Erklärung des Antragstellers</th></tr>
    <tr><td>1. Hiermit erkenne ich gem. § 3 die Richtlinien als verbindlich an.<br>2. Ich versichere, die Angaben vollständig und ordnungsgemäß erteilt zu haben.</td></tr>
</table>
<table class="sig"><tr><td><div class="linie">Saarlouis, den</div></td><td>{{ $as['vertretung'] ?? '' }}<div class="linie">Unterschrift des/der Vertretungsberechtigten</div></td></tr></table>

<div class="neu"></div>
<h1>Teilnehmerliste</h1>
<p>Für die <strong>{{ $event->title }}</strong> von <strong>{{ $event->starts_at->format('d.m.Y') }}</strong> bis <strong>{{ $ende->format('d.m.Y') }}</strong> in <strong>{{ $event->location }}</strong></p>
<table class="f">
    <tr><th class="kopf" style="width:7mm">Nr.</th><th class="kopf">Name und Vorname</th><th class="kopf">Wohnort</th><th class="kopf">Straße</th><th class="kopf">Geb.Dat.</th><th class="kopf" style="width:45mm">Unterschrift</th></tr>
    @foreach ($personen as $i => $p)
        <tr><td class="z">{{ $i + 1 }}</td><td>{{ $p['nachname'] }}, {{ $p['vorname'] }}{{ $p['funktion'] !== 'TN' ? ' ('.$p['funktion'].')' : '' }}</td><td>{{ trim(($p['plz'] ?? '').' '.($p['wohnort'] ?? '')) }}</td><td>{{ $p['strasse'] }}</td><td>{{ $p['geburtsdatum']?->format('d.m.Y') }}</td><td style="height:6mm"></td></tr>
    @endforeach
    @for ($j = 0; $j < 3; $j++)<tr><td class="z">{{ count($personen) + $j + 1 }}</td><td></td><td></td><td></td><td></td><td style="height:6mm"></td></tr>@endfor
</table>
<p style="margin-top:4mm">Die Richtigkeit der Teilnehmerliste und der durchgeführten Maßnahme wird bescheinigt:</p>
<table class="sig"><tr><td>{{ $as['leitung_name'] ?? '' }}{{ filled($as['leitung_anschrift'] ?? null) ? ', '.preg_replace('/\s*\R\s*/', ', ', $as['leitung_anschrift']) : '' }}<div class="linie">Der/Die Leiter/in (Name und Anschrift)</div></td><td><div class="linie">Unterschrift und Stempel</div></td></tr></table>
</body></html>
