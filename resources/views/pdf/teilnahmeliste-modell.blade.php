{{-- Teilnahmeliste in einem Modell (App\Support\Teilnahmelisten) mit Kopf und Unterschriften. Gerendert mit dompdf. --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Teilnahmeliste – {{ $event->title }}</title>
<style>
    @font-face { font-family: 'Arvo'; font-weight: bold; src: url('{{ $fonts }}/Arvo-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ $fonts }}/Carlito-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ $fonts }}/Carlito-Bold.ttf') format('truetype'); }
    @page { margin: 12mm 12mm 16mm; }
    body { font-family: 'Carlito', sans-serif; font-size: 10pt; color: #1c1f26; }
    h1 { font-family: 'Arvo', serif; font-size: 15pt; color: #003056; margin: 0 0 2mm; }
    .klein { font-size: 9pt; color: #5c6670; margin: 0; }
    .logo { float: right; height: 13mm; }
    table.kopf { width: auto; margin: 0 0 3mm; }
    table.kopf td { border: 0; padding: 0.4mm 4mm 0.4mm 0; }
    table.kopf td.k { color: #5c6670; font-size: 9pt; }
    table.liste { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { text-align: left; background: #ecdfcb; font-size: 9pt; padding: 1.5mm; }
    td { border-bottom: 0.2mm solid #c7bdad; padding: 2mm 1.5mm; vertical-align: middle; }
    td.nr { width: 7mm; color: #5c6670; }
    tr.betreuung td { background: #e3edf5; }
    .legende { margin-top: 3mm; font-size: 9pt; color: #5c6670; }
    .legende .farbe { display: inline-block; width: 6mm; height: 3.5mm; background: #e3edf5; border: 0.2mm solid #9db4c7; vertical-align: middle; }
    td.sig { width: 55mm; height: 11mm; }
    .unterschrift-bild { max-width: 50mm; max-height: 9mm; }
    .fuss { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8pt; color: #5c6670; }
</style>
</head>
<body>
    @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
    <h1>Teilnahmeliste</h1>
    <table class="kopf">
        @foreach ($kopf as $zeile)
            <tr>
                @foreach ($zeile as [$label, $wert])
                    <td class="k">{{ $label }}:</td><td @if (count($zeile) === 1 && count($kopf) > 1 && $kopf[0] !== $zeile) colspan="3" @endif><strong>{{ $wert }}</strong></td>
                @endforeach
            </tr>
        @endforeach
    </table>
    <p class="klein" style="margin-bottom: 3mm">{{ $bestaetigung }} Wer nicht digital unterschrieben hat, unterschreibt hier von Hand.</p>

    <table class="liste">
        <thead>
            <tr>
                <th>Nr.</th>
                @foreach ($modell['spalten'] as $spalte)<th>{{ $spalte['label'] }}</th>@endforeach
                <th>Unterschrift</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($zeilen as $zeile)
                <tr @class(['betreuung' => $modell['betreuung_markieren'] && $zeile['betreuung']])>
                    <td class="nr">{{ $loop->iteration }}</td>
                    @foreach ($zeile['werte'] as $wert)<td>{{ $wert }}</td>@endforeach
                    <td class="sig">{!! isset($unterschriften[$zeile['id']]) ? '<img class="unterschrift-bild" src="'.e($unterschriften[$zeile['id']]).'" alt="">' : '' !!}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 2 + count($modell['spalten']) }}">Noch keine bestätigten Anmeldungen.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($modell['betreuung_markieren'] && collect($zeilen)->contains('betreuung', true))
        <p class="legende"><span class="farbe"></span> = Betreuer*innen</p>
    @endif
    <p class="klein" style="margin-top: 5mm">Stand {{ now()->format('d.m.Y H:i') }} · {{ count($zeilen) }} Personen · Modell: {{ $modell['name'] }}@if ($event->contact_name || $event->contact_email) · Veranstaltungsleitung: {{ $event->contact_name }}{{ $event->contact_email ? ' · '.$event->contact_email : '' }}@endif</p>
    <div class="fuss">{{ config('dpsg.site.name') }} · Teilnahmeliste „{{ $event->title }}“ · vertraulich</div>
</body>
</html>
