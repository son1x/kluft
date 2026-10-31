{{-- Förderschätzung mit Rechenweg je Zuschussgeber (ZU-18). dompdf. --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Förderschätzung</title>
<style>
    @font-face { font-family: 'Arvo'; font-weight: bold; src: url('{{ $fonts }}/Arvo-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ $fonts }}/Carlito-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ $fonts }}/Carlito-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-style: italic; src: url('{{ $fonts }}/Carlito-Italic.ttf') format('truetype'); }
    @page { margin: 16mm 18mm 18mm; }
    body { font-family: 'Carlito', sans-serif; font-size: 10.5pt; color: #1c1f26; }
    h1 { font-family: 'Arvo', serif; font-size: 17pt; color: #003056; margin: 0 0 1mm; }
    h2 { font-family: 'Arvo', serif; font-size: 11.5pt; color: #003056; margin: 6mm 0 1.5mm; border-bottom: 0.8mm solid #810a1a; padding-bottom: 1mm; }
    .logo { float: right; height: 14mm; }
    .klein { font-size: 8.5pt; color: #5c6670; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 1mm 0; vertical-align: top; }
    .betrag { text-align: right; white-space: nowrap; width: 30mm; }
    .geber { margin-top: 3mm; padding: 2.5mm 3mm; background: #f7f2ea; }
    .geber .titel { font-weight: bold; }
    .hinweis { font-style: italic; color: #5c6670; font-size: 9pt; }
    .summe td { border-top: 0.3mm solid #1c1f26; font-weight: bold; padding-top: 1.5mm; }
    .ergebnis { margin-top: 4mm; padding: 3mm; background: #003056; color: #fff; font-size: 12pt; }
</style>
</head>
<body>
@php($euro = fn (?int $c) => number_format(($c ?? 0) / 100, 2, ',', '.').' €')
@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
<h1>Förderschätzung</h1>
<p><strong>{{ $event->title }}</strong> · {{ $event->dateLabel() }} · {{ $event->location }}<br>
<span class="klein">{{ \App\Support\Zuschuesse::ARTEN[$event->zuschuss_art] ?? 'Art der Maßnahme nicht gewählt' }} · {{ count($event->days()) }} Kalendertag(e) · {{ $zaehlung['gesamt']['tn'] }} Teilnehmende, {{ $zaehlung['gesamt']['betreuung'] }} Betreuende{{ $zaehlung['gesamt']['referenten'] ? ', '.$zaehlung['gesamt']['referenten'].' Referent*innen' : '' }} (bestätigt und anwesend) · Stand {{ now()->format('d.m.Y') }}</span></p>

<h2>Je Zuschussgeber</h2>
@foreach ($schaetzung['geber'] as $g)
    <div class="geber">
        <table><tr><td class="titel">{{ $g['name'] }}</td><td class="betrag titel">{{ $euro($g['betrag']) }}</td></tr></table>
        @foreach ($g['zeilen'] as $z)<div>{{ $z }}</div>@endforeach
        @foreach ($g['hinweise'] as $h)<div class="hinweis">{{ $h }}</div>@endforeach
        <div class="klein">Grundlage: {{ $g['richtlinie'] }}</div>
    </div>
@endforeach

<h2>Ergebnis</h2>
@php($f = $schaetzung['finanzen'])
<table>
    <tr><td>Rechnerisch zusammen</td><td class="betrag">{{ $euro($schaetzung['summe']) }}</td></tr>
    @if ($schaetzung['ungedeckt'] !== null)
        <tr><td>Ausgaben laut Kostenplan</td><td class="betrag">{{ $euro($f['ausgaben']) }}</td></tr>
        <tr><td>Einnahmen (Teilnahmebeiträge {{ $euro($f['teilnahmebeitraege'] ?? 0) }}, Spenden/andere {{ $euro(($f['spenden'] ?? 0) + ($f['sonstige'] ?? 0)) }})</td><td class="betrag">− {{ $euro($f['einnahmen']) }}</td></tr>
        <tr class="summe"><td>Ungedeckte Kosten</td><td class="betrag">{{ $euro($schaetzung['ungedeckt']) }}</td></tr>
    @endif
</table>
<div class="ergebnis">Höchstens zu erwarten: <strong>{{ $euro($schaetzung['hoechstens']) }}</strong>@if ($schaetzung['ungedeckt'] !== null && $schaetzung['summe'] > $schaetzung['ungedeckt']) <span style="font-size:10pt">(gedeckelt auf die ungedeckten Kosten; die Zuschüsse würden {{ $euro($schaetzung['summe'] - $schaetzung['ungedeckt']) }} mehr hergeben)</span>@endif</div>
@foreach ($schaetzung['hinweise'] as $h)<p class="hinweis">{{ $h }}</p>@endforeach
<p class="klein" style="margin-top:6mm">Schätzung ohne Gewähr, erstellt mit Kluft nach den genannten Richtlinien und den Angaben in den Anmeldungen. Ein Rechtsanspruch besteht nicht; über die Förderung entscheiden die Zuschussgeber im Rahmen ihrer Haushaltsmittel.</p>
</body>
</html>
