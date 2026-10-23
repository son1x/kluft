<!doctype html>
{{-- Auswertung einer Umfrage als DIN-A4-PDF (dompdf). Erwartet: $umfrage, $auswertung, $fonts --}}
<html lang="de">
<head>
<meta charset="utf-8" />
<style>
    @page { margin: 18mm 16mm 18mm 16mm; }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ $fonts }}/Carlito-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ $fonts }}/Carlito-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Arvo'; font-weight: bold; src: url('{{ $fonts }}/Arvo-Bold.ttf') format('truetype'); }
    body { font-family: 'Carlito', sans-serif; font-size: 10.5pt; color: #1c1f26; }
    h1 { font-family: 'Arvo', serif; font-size: 18pt; color: #003056; margin: 0 0 4pt; }
    .meta { font-size: 9pt; color: #6b7280; margin-bottom: 12pt; }
    .fuss { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8pt; color: #6b7280; }
</style>
</head>
<body>
    <div class="fuss">{{ config('dpsg.site.name') }} · Auswertung „{{ $umfrage->titel }}“ · Stand {{ now()->format('d.m.Y H:i') }}</div>
    <h1>{{ $umfrage->titel }}</h1>
    <p class="meta">
        {{ $auswertung['teilnahmen'] }} Teilnahmen
        @if ($auswertung['tage'] !== []) · {{ \Illuminate\Support\Carbon::parse(array_key_first($auswertung['tage']))->format('d.m.Y') }} bis {{ \Illuminate\Support\Carbon::parse(array_key_last($auswertung['tage']))->format('d.m.Y') }}@endif
        · {{ $umfrage->anonym ? 'anonym' : 'mit Namen' }}
        @if ($umfrage->event) · Teilnehmende von „{{ $umfrage->event->title }}“@endif
    </p>
    @foreach ($auswertung['fragen'] as $e)
        @include('umfragen.partials.frage-ergebnis', ['e' => $e])
    @endforeach
</body>
</html>
