{{-- Küchenliste zum Aushängen (App\Services\KuechenlistePdf). Gerendert mit dompdf. --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Küchenliste – {{ $event->title }}</title>
<style>
    @font-face { font-family: 'Arvo'; font-weight: bold; src: url('{{ $fonts }}/Arvo-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ $fonts }}/Carlito-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ $fonts }}/Carlito-Bold.ttf') format('truetype'); }
    @page { margin: 12mm 12mm 16mm; }
    body { font-family: 'Carlito', sans-serif; font-size: 12pt; color: #1c1f26; }
    h1 { font-family: 'Arvo', serif; font-size: 18pt; color: #003056; margin: 0 0 1mm; }
    h2 { font-family: 'Arvo', serif; font-size: 13pt; color: #003056; margin: 6mm 0 2mm; }
    .klein { font-size: 9pt; color: #5c6670; margin: 0; }
    .vertraulich { margin: 3mm 0; padding: 2mm 3mm; border: 0.5mm solid #810a1a; color: #810a1a; font-weight: bold; font-size: 10pt; }
    .logo { float: right; height: 13mm; }
    table { border-collapse: collapse; }
    .breit { width: 100%; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { text-align: left; background: #ecdfcb; font-size: 10pt; padding: 1.5mm 2mm; }
    td { border-bottom: 0.2mm solid #c7bdad; padding: 2.5mm 2mm; vertical-align: top; }
    td.zahl, th.zahl { text-align: right; font-weight: bold; font-size: 14pt; white-space: nowrap; }
    .kacheln td { border: 0.3mm solid #c7bdad; text-align: center; padding: 2mm 4mm; }
    .kacheln .n { font-size: 20pt; font-weight: bold; color: #003056; }
    .wert { font-weight: bold; color: #810a1a; }
    .fuss { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8pt; color: #5c6670; }
</style>
</head>
<body>
    @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
    <h1>Küche: {{ $event->title }}</h1>
    <p class="klein">{{ $event->dateLabel() }}{{ $event->location ? ' · '.$event->location : '' }} · Stand {{ now()->format('d.m.Y H:i') }} · {{ $gesamt }} bestätigte Teilnehmende</p>
    <p class="vertraulich">Nur in der Küche aushängen, nicht für Gäste sichtbar. Enthält Gesundheitsangaben: nach der Veranstaltung vernichten.</p>

    <h2>Essen je Tag</h2>
    @if (count($tage) > 1)
        <table class="kacheln"><tr>
            @foreach ($tage as $tag => $n)<td><div class="klein">{{ $tag }}</div><div class="n">{{ $n }}</div></td>@endforeach
        </tr></table>
        <p class="klein" style="margin-top: 1mm">Ohne Angabe der Tage zählt die ganze Zeit. Leitung und Küchenteam ggf. dazurechnen.</p>
    @else
        <table class="kacheln"><tr><td><div class="klein">Personen</div><div class="n">{{ $gesamt }}</div></td></tr></table>
    @endif

    @foreach ($verteilung as $feld => $zahlen)
        <h2>{{ $feld }}{{ count($tage) > 1 ? ' je Tag' : '' }}</h2>
        <table>
            @if (count($tage) > 1)
                <thead><tr><th></th>@foreach (array_keys($tage) as $tag)<th class="zahl" style="font-size: 9pt">{{ $tag }}</th>@endforeach</tr></thead>
            @endif
            @foreach ($zahlen as $antwort => $jeTag)
                <tr><td style="padding-right: 8mm">{{ $antwort }}</td>@foreach ($jeTag as $n)<td class="zahl">{{ $n }}</td>@endforeach</tr>
            @endforeach
        </table>
    @endforeach

    <h2>Besonderheiten ({{ $besonderheiten->count() }})</h2>
    @if ($besonderheiten->isEmpty())
        <p>Keine besonderen Angaben.</p>
    @else
        @php($mitStamm = $besonderheiten->contains(fn (array $person) => filled($person['stamm'])))
        <table class="breit">
            <thead>
                <tr>
                    <th>Name</th>
                    @if ($mitStamm)<th>Stamm</th>@endif
                    @foreach ($spalten as $spalte)<th>{{ $spalte }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($besonderheiten as $p)
                    <tr>
                        <td><strong>{{ $p['name'] }}</strong></td>
                        @if ($mitStamm)<td>{{ $p['stamm'] }}</td>@endif
                        @foreach (array_keys($spalten) as $k)<td class="wert">{{ $p['werte'][$k] ?? '' }}</td>@endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="fuss">{{ config('dpsg.site.name') }} · Küchenliste „{{ $event->title }}“ · vertraulich</div>
</body>
</html>
