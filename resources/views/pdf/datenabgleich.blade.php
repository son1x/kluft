{{-- Unterschiedsliste NaMi-Datenabgleich (Querformat). --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>NaMi-Datenabgleich</title>@include('pdf.partials.zuschuss-stil')</head>
<body>
<h1>NaMi-Datenabgleich</h1>
<p class="unter">{{ config('dpsg.site.name') }} · Stand {{ now()->format('d.m.Y') }} · Angaben aus „Meine Daten aktualisieren“ und Anmeldungen im Vergleich zu NaMi. {{ $stimmt }} Person(en) ohne Unterschied sind nicht aufgeführt. Bitte in NaMi korrigieren und im Backend abhaken.</p>
<table class="f">
    <tr><th class="kopf">Person</th><th class="kopf">Mitgliedsnr.</th><th class="kopf">Ergebnis</th><th class="kopf">Feld</th><th class="kopf">laut Meldung</th><th class="kopf">laut NaMi</th><th class="kopf">erledigt</th></tr>
    @foreach ($zeilen as $z)
        @php($felder = $z->ergebnis['felder'] ?? [])
        @foreach ($felder ?: [null] as $i => $f)
            <tr>
                @if ($i === 0)
                    <td rowspan="{{ max(1, count($felder)) }}"><strong>{{ $z->ergebnis['name'] ?? '' }}</strong><br><span class="klein">{{ $z->quelle === 'meldung' ? 'Meldung' : 'Anmeldung' }} {{ $z->ergebnis['datum'] ? \Illuminate\Support\Carbon::parse($z->ergebnis['datum'])->format('d.m.Y') : '' }}</span></td>
                    <td rowspan="{{ max(1, count($felder)) }}">{{ $z->ergebnis['nummer'] ?? '' }}</td>
                    <td rowspan="{{ max(1, count($felder)) }}">{{ \App\Models\DatenabgleichZeile::STATUS[$z->status] ?? $z->status }}</td>
                @endif
                <td>{{ $f['feld'] ?? '' }}</td><td>{{ $f['meldung'] ?? '' }}</td><td>{{ $f['nami'] ?? '' }}</td>
                @if ($i === 0)<td rowspan="{{ max(1, count($felder)) }}">{{ $z->erledigt_am ? $z->erledigt_am->format('d.m.Y') : '' }}</td>@endif
            </tr>
        @endforeach
    @endforeach
</table>
</body></html>
