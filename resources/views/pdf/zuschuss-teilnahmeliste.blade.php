{{-- Teilnahmeliste für Zuschussanträge (Querformat), für Jugendamt,
     Landesjugendamt (Vordruck B), Stadt Saarlouis und Bistum Trier (KJP).
     Gerendert mit dompdf. --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Teilnahmeliste</title>
<style>
    @font-face { font-family: 'Arvo'; font-weight: bold; src: url('{{ $fonts }}/Arvo-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ $fonts }}/Carlito-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ $fonts }}/Carlito-Bold.ttf') format('truetype'); }
    @page { margin: 12mm 12mm 14mm; }
    body { font-family: 'Carlito', sans-serif; font-size: 9.5pt; color: #1c1f26; }
    h1 { font-family: 'Arvo', serif; font-size: 15pt; color: #003056; margin: 0 0 2mm; }
    h2 { font-family: 'Arvo', serif; font-size: 11pt; color: #003056; margin: 5mm 0 1.5mm; }
    .logo { float: right; height: 13mm; }
    .kopf td { padding: 0.6mm 4mm 0.6mm 0; vertical-align: top; }
    .kopf .l { color: #5c6670; }
    table.liste { width: 100%; border-collapse: collapse; }
    table.liste th { background: #ecdfcb; font-size: 8.5pt; text-align: left; padding: 1.2mm 1.5mm; border: 0.2mm solid #c7bdad; }
    table.liste td { border: 0.2mm solid #c7bdad; padding: 1.8mm 1.5mm; height: 6mm; }
    .nr { width: 7mm; text-align: right; }
    td.sig { width: 42mm; padding-top: 0.5mm; padding-bottom: 0.5mm; }
    .unterschrift-bild { max-width: 40mm; max-height: 9mm; }
    .klein { font-size: 8pt; color: #5c6670; }
    .summen td { padding: 0.8mm 6mm 0.8mm 0; }
    .unterschrift { margin-top: 10mm; width: 95mm; border-top: 0.3mm solid #1c1f26; padding-top: 1mm; font-size: 8.5pt; }
</style>
</head>
<body>
@php
    $leer = 3;
    $zeile = function (array $p, bool $juleica) {
        return [
            'name' => $p['nachname'],
            'vorname' => $p['vorname'],
            'strasse' => $p['strasse'],
            'ort' => trim(($p['plz'] ?? '').' '.($p['wohnort'] ?? '')),
            'geb' => $p['geburtsdatum']?->format('d.m.Y'),
            'alter' => $p['alter'],
            'g' => $p['geschlecht'],
            'funktion' => $p['funktion'],
            'juleica' => $juleica ? ($p['juleica'] ? 'ja' : 'nein') : null,
        ];
    };
@endphp
@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
<h1>Teilnahmeliste</h1>
<table class="kopf">
    <tr><td class="l">Antragsteller</td><td><strong>{{ config('dpsg.site.name') }}</strong>, {{ config('dpsg.contact.street') }}, {{ config('dpsg.contact.city') }}</td>
        <td class="l">Beginn</td><td>{{ $event->starts_at->format('d.m.Y H:i') }}</td></tr>
    <tr><td class="l">Maßnahme</td><td>{{ $event->title }} ({{ \App\Support\Zuschuesse::ARTEN[$event->zuschuss_art] ?? '' }})</td>
        <td class="l">Ende</td><td>{{ ($event->ends_at ?? $event->starts_at)->format('d.m.Y H:i') }}</td></tr>
    <tr><td class="l">Ort der Durchführung</td><td>{{ $event->location }}</td><td class="l">Tage</td><td>{{ count($event->days()) }}</td></tr>
</table>
<p class="klein">Die Teilnahmeliste ist vollständig auszufüllen und rechtsverbindlich zu unterschreiben. Funktion: TN Teilnehmer*in, B Betreuer*in, R Referent*in, L Leiter*in.</p>

@foreach ([['Betreuungspersonen, Leitung und Referent*innen', $betreuung, true], ['Teilnehmende', $teilnehmende, false]] as [$titel, $liste, $juleica])
    <h2>{{ $titel }}</h2>
    <table class="liste">
        <thead><tr>
            <th class="nr">Nr.</th><th>Name</th><th>Vorname</th><th>Straße</th><th>PLZ, Wohnort</th><th>Geb.-Datum</th><th>Alter</th><th>m/w/d</th><th>Funktion</th>
            @if ($juleica)<th>Juleica</th>@endif
            <th class="sig">Unterschrift</th>
        </tr></thead>
        <tbody>
            @foreach ($liste as $i => $p)
                @php($z = $zeile($p, $juleica))
                <tr><td class="nr">{{ $i + 1 }}</td><td>{{ $z['name'] }}</td><td>{{ $z['vorname'] }}</td><td>{{ $z['strasse'] }}</td><td>{{ $z['ort'] }}</td><td>{{ $z['geb'] }}</td><td>{{ $z['alter'] }}</td><td>{{ $z['g'] }}</td><td>{{ $z['funktion'] }}</td>
                    @if ($juleica)<td>{{ $z['juleica'] }}</td>@endif
                    <td class="sig">{!! isset($unterschriften[$p['id']]) ? '<img class="unterschrift-bild" src="'.e($unterschriften[$p['id']]).'" alt="">' : '' !!}</td></tr>
            @endforeach
            @for ($j = 0; $j < $leer; $j++)
                <tr><td class="nr">{{ count($liste) + $j + 1 }}</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>@if ($juleica)<td></td>@endif<td class="sig"></td></tr>
            @endfor
        </tbody>
    </table>
@endforeach

<table class="summen" style="margin-top: 5mm">
    @php($g = $zaehlung['geschlecht'])
    <tr><td>Zahl der Teilnehmer: <strong>{{ $g['tn']['m'] }}</strong></td><td>Teilnehmerinnen: <strong>{{ $g['tn']['w'] }}</strong></td><td>Teilnehmende divers: <strong>{{ $g['tn']['d'] }}</strong></td><td>gesamt: <strong>{{ $zaehlung['gesamt']['tn'] }}</strong></td></tr>
    <tr><td>Zahl der Betreuer: <strong>{{ $g['betreuung']['m'] }}</strong></td><td>Betreuerinnen: <strong>{{ $g['betreuung']['w'] }}</strong></td><td>Betreuende divers: <strong>{{ $g['betreuung']['d'] }}</strong></td><td>gesamt: <strong>{{ $zaehlung['gesamt']['betreuung'] }}</strong></td></tr>
</table>
<p class="klein">Stand {{ now()->format('d.m.Y') }}; Zählung ohne die leeren Zeilen für Nachträge.</p>
<div class="unterschrift">Die Richtigkeit der Angaben wird bestätigt: Name, rechtsverbindliche Unterschrift</div>
</body>
</html>
