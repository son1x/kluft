{{-- Ausgabeliste (je Stufe, Querformat) bzw. Ausgabescheine (je Bestellung)
     der Sammelbestellung mit Unterschriftsfeld. Gerendert mit dompdf. --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{{ $art === 'liste' ? 'Ausgabeliste' : 'Ausgabescheine' }}</title>
<style>
    @font-face { font-family: 'Arvo'; font-weight: bold; src: url('{{ $fonts }}/Arvo-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ $fonts }}/Carlito-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ $fonts }}/Carlito-Bold.ttf') format('truetype'); }
    @page { margin: 14mm 14mm 16mm; }
    body { font-family: 'Carlito', sans-serif; font-size: 10.5pt; color: #1c1f26; }
    h1 { font-family: 'Arvo', serif; font-size: 16pt; color: #003056; margin: 0 0 1mm; }
    h2 { font-family: 'Arvo', serif; font-size: 12pt; color: #003056; margin: 6mm 0 2mm; border-bottom: 0.8mm solid #810a1a; padding-bottom: 1mm; }
    .klein { font-size: 9pt; color: #5c6670; }
    .logo { float: right; height: 14mm; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; background: #ecdfcb; font-size: 9.5pt; padding: 1.5mm 2mm; }
    td { border-bottom: 0.2mm solid #c7bdad; padding: 2.2mm 2mm; vertical-align: top; }
    td.feld { width: 55mm; }
    td.datum { width: 25mm; }
    .neu { page-break-before: always; }
    .unterschrift { margin-top: 14mm; width: 90mm; border-top: 0.3mm solid #1c1f26; padding-top: 1.5mm; font-size: 9.5pt; }
    .set { font-size: 8.5pt; color: #810a1a; font-weight: bold; }
</style>
</head>
<body>
@if ($art === 'liste')
    @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
    <h1>Ausgabeliste – {{ $runde->name }}</h1>
    <p class="klein">{{ config('dpsg.site.name') }} · Sammelbestellung beim Rüsthaus · Stand {{ now()->format('d.m.Y') }}. Mit der Unterschrift bestätigt das Mitglied bzw. die erziehungsberechtigte Person den Empfang.</p>
    @forelse ($gruppen as $stufe => $positionen)
        <h2>{{ $stufe }}</h2>
        <table>
            <thead><tr><th>Mitglied</th><th>Artikel</th><th>Anzahl</th><th>erhalten am</th><th>Unterschrift</th></tr></thead>
            <tbody>
            @foreach ($positionen as $p)
                <tr>
                    <td>{{ $p->mitglied }}</td>
                    <td>@if ($p->set)<span class="set">{{ $p->set->name }}:</span> @endif{{ \App\Services\Sammelbestellung\Ausgabe::artikelText($p) }}</td>
                    <td>{{ $p->menge }}</td>
                    <td class="datum">{{ $p->ausgegeben_am?->format('d.m.Y') }}</td>
                    <td class="feld">{{ $p->ausgegeben_am ? 'abgehakt' : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @empty
        <p>Keine bezahlten Positionen.</p>
    @endforelse
@else
    @forelse ($scheine as $positionen)
        @php($b = $positionen->first()->bestellung)
        <div class="{{ $loop->first ? '' : 'neu' }}">
            @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
            <h1>Ausgabeschein</h1>
            <p class="klein">{{ config('dpsg.site.name') }} · Sammelbestellung „{{ $runde->name }}“ · bestellt von {{ $b->name }}</p>
            <table style="margin-top: 6mm">
                <thead><tr><th>Mitglied</th><th>Stufe</th><th>Artikel</th><th>Anzahl</th></tr></thead>
                <tbody>
                @foreach ($positionen->sortBy(fn ($p) => mb_strtolower($p->mitglied).'|'.($p->set_gruppe ?? 'zz').'|'.$p->id) as $p)
                    <tr>
                        <td>{{ $p->mitglied }}</td>
                        <td>{{ $p->stufe ? config('dpsg.stages.'.$p->stufe.'.label', $p->stufe) : '' }}</td>
                        <td>@if ($p->set)<span class="set">{{ $p->set->name }}:</span> @endif{{ \App\Services\Sammelbestellung\Ausgabe::artikelText($p) }}</td>
                        <td>{{ $p->menge }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p style="margin-top: 8mm">Die oben genannten Artikel habe ich vollständig erhalten.</p>
            <div class="unterschrift">Datum, Unterschrift (Mitglied bzw. erziehungsberechtigte Person)</div>
        </div>
    @empty
        <p>Keine bezahlten Positionen.</p>
    @endforelse
@endif
</body>
</html>
