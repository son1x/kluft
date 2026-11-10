{{-- Teilnehmendenliste für unterwegs (App\Services\TeilnehmendeListePdf). Gerendert mit dompdf. --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Teilnehmende – {{ $event->title }}</title>
<style>
    @font-face { font-family: 'Arvo'; font-weight: bold; src: url('{{ $fonts }}/Arvo-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ $fonts }}/Carlito-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ $fonts }}/Carlito-Bold.ttf') format('truetype'); }
    @page { margin: 12mm 12mm 16mm; }
    body { font-family: 'Carlito', sans-serif; font-size: 10pt; color: #1c1f26; }
    h1 { font-family: 'Arvo', serif; font-size: 15pt; color: #003056; margin: 0 0 1mm; }
    .klein { font-size: 9pt; color: #5c6670; margin: 0; }
    .vertraulich { margin: 3mm 0; padding: 2mm 3mm; border: 0.5mm solid #810a1a; color: #810a1a; font-weight: bold; font-size: 9.5pt; }
    .logo { float: right; height: 13mm; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { text-align: left; background: #ecdfcb; font-size: 9pt; padding: 1.5mm 1.5mm; }
    td { border-bottom: 0.2mm solid #c7bdad; padding: 2mm 1.5mm; vertical-align: top; }
    td.nr { width: 7mm; color: #5c6670; }
    th.tag, td.tag { width: 14mm; text-align: center; white-space: nowrap; }
    th.tag { font-size: 8pt; }
    .kasten { display: inline-block; width: 4mm; height: 4mm; border: 0.3mm solid #1c1f26; }
    .wl { font-size: 8pt; color: #810a1a; font-weight: bold; }
    .fuss { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8pt; color: #5c6670; }
</style>
</head>
<body>
    @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
    <h1>{{ $event->title }}</h1>
    @php($wl = $personen->where('warteliste', true)->count())
    <p class="klein">{{ $event->dateLabel() }}{{ $event->location ? ' · '.$event->location : '' }} · Stand {{ now()->format('d.m.Y H:i') }} · {{ $personen->count() - $wl }} bestätigt{{ $wl > 0 ? ', '.$wl.' auf der Warteliste' : '' }}</p>
    <p class="vertraulich">Vertraulich: nur für die Leitung der Veranstaltung. Nicht weitergeben, nach der Veranstaltung vernichten.</p>

    <table>
        <thead>
            <tr>
                <th>Nr.</th>
                <th>Name</th>
                @foreach ($spalten as $spalte)<th>{{ $spalte }}</th>@endforeach
                @if ($mitSorge)<th>Angemeldet von (sorgeberechtigt)</th>@endif
                @foreach ($tage as $tag)<th class="tag">{{ $tag === 'anwesend' ? 'Da' : $tag }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($personen as $p)
                <tr>
                    <td class="nr">{{ $loop->iteration }}</td>
                    <td><strong>{{ $p['name'] }}</strong>@if ($p['warteliste'])<br><span class="wl">Warteliste</span>@endif</td>
                    @foreach ($p['werte'] as $wert)<td>{{ $wert }}</td>@endforeach
                    @if ($mitSorge)<td>{{ $p['sorge'] }}</td>@endif
                    @foreach ($tage as $tag)<td class="tag"><span class="kasten"></span></td>@endforeach
                </tr>
            @empty
                <tr><td colspan="{{ 2 + count($spalten) + ($mitSorge ? 1 : 0) + count($tage) }}">Noch keine bestätigten Anmeldungen.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($event->contact_name || $event->contact_email)
        <p class="klein" style="margin-top: 5mm">Veranstaltungsleitung: {{ $event->contact_name }}{{ $event->contact_email ? ' · '.$event->contact_email : '' }}</p>
    @endif
    <div class="fuss">{{ config('dpsg.site.name') }} · Teilnehmendenliste „{{ $event->title }}“ · vertraulich</div>
</body>
</html>
