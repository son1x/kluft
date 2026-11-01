{{-- Allgemeiner Antrag für eigene Zuschussgeber (Verwaltung → Zuschussgeber):
     Antragsteller, Maßnahme, Zusammensetzung, Kosten- und Finanzierungsplan,
     beantragter Betrag mit Rechenweg, Teilnehmerliste mit Unterschrift. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Antrag {{ $geber['name'] }}</title>@include('pdf.partials.zuschuss-stil')</head>
<body>
@php
    $euro = fn (?int $c) => $c === null ? '' : number_format($c / 100, 2, ',', '.').' €';
    $b = (array) $event->zuschuss_bericht;
    $f = $finanzen;
    $r = $geber['regeln'];
    $ende = $event->ends_at ?? $event->starts_at;
    $g = $zaehlung['geschlecht'];
    $beantragt = $stand['beantragt'] ?? null;
    $schaetzung = $geber['schaetzung']['betrag'] ?? 0;
@endphp
<h1>Antrag auf Zuschuss</h1>
<p class="unter">an: <strong>{{ $geber['name'] }}</strong>{!! filled($r['anschrift'] ?? null) ? ', '.nl2br(e(preg_replace('/\s*\R\s*/', ', ', $r['anschrift']))) : '' !!}{{ filled($r['richtlinie'] ?? null) ? ' · nach: '.$r['richtlinie'] : '' }}</p>

<table class="f">
    <tr><th class="kopf" colspan="4">Antragsteller</th></tr>
    <tr><td class="l">Verband/Gruppe</td><td colspan="3"><strong>{{ $as['name'] }}</strong></td></tr>
    <tr><td class="l">Anschrift</td><td colspan="3">{{ $as['anschrift'] }}</td></tr>
    <tr><td class="l">Ansprechperson</td><td>{{ $as['rueckfragen'] }}</td><td class="l">Tel. / E-Mail</td><td>{{ $as['telefon'] }}{{ $as['email'] ? ' / '.$as['email'] : '' }}</td></tr>
    <tr><td class="l">Bankverbindung</td><td colspan="3">{{ $as['inhaber'] }}{{ $as['bank'] ? ', '.$as['bank'] : '' }}{{ $as['iban'] ? ', IBAN '.$as['iban'] : '' }}{{ $as['bic'] ? ', BIC '.$as['bic'] : '' }}</td></tr>
</table>

<table class="f">
    <tr><th class="kopf" colspan="4">Maßnahme</th></tr>
    <tr><td class="l">Titel</td><td colspan="3"><strong>{{ $event->title }}</strong>{{ filled($b['thema'] ?? null) ? ' – '.$b['thema'] : '' }}</td></tr>
    <tr><td class="l">Art</td><td>{{ \App\Support\Zuschuesse::ARTEN[$event->zuschuss_art] ?? '' }}</td><td class="l">Ort</td><td>{{ $event->location }}</td></tr>
    <tr><td class="l">Beginn</td><td>{{ $event->starts_at->format('d.m.Y, H:i') }} Uhr</td><td class="l">Ende</td><td>{{ $ende->format('d.m.Y, H:i') }} Uhr ({{ count($event->days()) }} Tage)</td></tr>
    <tr><td class="l">Teilnehmende</td><td colspan="3">{{ $zaehlung['gesamt']['tn'] }} ({{ $g['tn']['w'] }} w, {{ $g['tn']['m'] }} m, {{ $g['tn']['d'] }} d) · Betreuung: {{ $zaehlung['gesamt']['betreuung'] }}{{ $zaehlung['gesamt']['referenten'] ? ' · Referent*innen: '.$zaehlung['gesamt']['referenten'] : '' }}</td></tr>
    @if (filled($b['methodik'] ?? null))<tr><td class="l">Inhalt, Ziel</td><td colspan="3">{!! nl2br(e($b['methodik'])) !!}</td></tr>@endif
</table>

<table class="f">
    <tr><th class="kopf" colspan="2">Ausgaben</th><th class="kopf" colspan="2">Einnahmen</th></tr>
    <tr><td>Fahrt</td><td class="z">{{ $euro($f['fahrt'] ?? null) }}</td><td>Teilnahmebeiträge</td><td class="z">{{ $euro($f['teilnahmebeitraege'] ?? null) }}</td></tr>
    <tr><td>Unterkunft</td><td class="z">{{ $euro($f['unterkunft'] ?? null) }}</td><td>Spenden, andere Zuschüsse</td><td class="z">{{ $euro($f['spenden'] ?? null) }}</td></tr>
    <tr><td>Verpflegung</td><td class="z">{{ $euro($f['verpflegung'] ?? null) }}</td><td>Sonstige</td><td class="z">{{ $euro($f['sonstige'] ?? null) }}</td></tr>
    <tr><td>Raummieten, Material, Durchführung</td><td class="z">{{ $euro((($f['raummiete'] ?? 0) + ($f['material'] ?? 0) + ($f['durchfuehrung'] ?? 0)) ?: null) }}</td><td></td><td></td></tr>
    <tr><td>Honorare</td><td class="z">{{ $euro($f['honorare'] ?? null) }}</td><td></td><td></td></tr>
    <tr><td><strong>Gesamtausgaben</strong></td><td class="z"><strong>{{ $euro($f['ausgaben']) }}</strong></td><td><strong>Gesamteinnahmen</strong></td><td class="z"><strong>{{ $euro($f['einnahmen']) }}</strong></td></tr>
    <tr><td colspan="2" class="z"><strong>Ungedeckte Kosten</strong></td><td colspan="2" class="z"><strong>{{ $euro($f['ungedeckt']) }}</strong></td></tr>
</table>

@if ($dritte !== [])
<table class="f">
    <tr><th class="kopf">Bewilligte Zuschüsse anderer Stellen</th><th class="kopf">Bescheid / Aktenzeichen</th><th class="kopf z">Betrag</th></tr>
    @foreach ($dritte as $d)<tr><td>{{ $d['name'] }}</td><td>{{ trim(($d['datum'] ?? '').' '.($d['aktenzeichen'] ?? '')) }}</td><td class="z">{{ $euro($d['betrag']) }}</td></tr>@endforeach
</table>
@endif

<table class="f">
    <tr><th class="kopf" colspan="2">Beantragter Zuschuss</th></tr>
    <tr><td style="width:70%">@foreach (($geber['schaetzung']['zeilen'] ?? []) as $z){{ $z }}<br>@endforeach</td><td class="z"><strong>{{ $euro($beantragt ?? $schaetzung) }}</strong></td></tr>
</table>

<table class="sig"><tr><td><div class="linie">Ort, Datum</div></td><td>{{ $as['vertretung'] ?? '' }}<div class="linie">Unterschrift des/der Vertretungsberechtigten</div></td></tr></table>

<div class="neu"></div>
<h1>Teilnehmerliste</h1>
<p>{{ $event->title }}, {{ $event->starts_at->format('d.m.Y') }} bis {{ $ende->format('d.m.Y') }}, {{ $event->location }}</p>
<table class="f">
    <tr><th class="kopf" style="width:7mm">Nr.</th><th class="kopf">Name, Vorname</th><th class="kopf">Funktion</th><th class="kopf">Anschrift</th><th class="kopf">Geb.-Datum</th><th class="kopf" style="width:40mm">Unterschrift</th></tr>
    @foreach ($personen as $i => $p)
        <tr><td class="z">{{ $i + 1 }}</td><td>{{ $p['nachname'] }}, {{ $p['vorname'] }}</td><td>{{ $p['funktion'] }}</td><td>{{ $p['strasse'] }}, {{ trim(($p['plz'] ?? '').' '.($p['wohnort'] ?? '')) }}</td><td>{{ $p['geburtsdatum']?->format('d.m.Y') }}</td><td style="height:6mm"></td></tr>
    @endforeach
</table>
<p class="klein">TN = Teilnehmer*in, B = Betreuer*in, L = Leiter*in, R = Referent*in</p>
<table class="sig"><tr><td>{{ $as['leitung_name'] ?? '' }}<div class="linie">Leiter*in der Maßnahme</div></td><td><div class="linie">Unterschrift</div></td></tr></table>
<p class="klein" style="margin-top:4mm">Erstellt mit Kluft (allgemeiner Antrag). Hat der Zuschussgeber einen eigenen Vordruck, die Angaben bitte dorthin übertragen.</p>
</body></html>
