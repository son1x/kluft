{{-- Formular A „Antrag und Nachweis zur Förderung der Kinder- und Jugendarbeit
     bei Land, Kreisen und Regionalverband“ (Saarland, Stand 14.12.2018),
     Nachbau mit den Feldern des Vordrucks. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Formular A</title>@include('pdf.partials.zuschuss-stil')</head>
<body>
@php
    $x = fn (bool $an) => '<span class="box">'.($an ? 'X' : '&nbsp;').'</span>';
    $euro = fn (?int $c) => $c === null ? '' : number_format($c / 100, 2, ',', '.').' €';
    $kreise = ['HOM', 'MZG', 'NK', 'SB', 'SLS', 'WND', 'SONST'];
    $f = $finanzen;
    $ende = $event->ends_at ?? $event->starts_at;
@endphp
<h1>Formular A – Antrag und Nachweis</h1>
<p class="unter">zur Förderung der Kinder- und Jugendarbeit bei Land, Kreisen und Regionalverband · an das örtlich zuständige Jugendamt und das Landesjugendamt des Saarlandes</p>

<table class="f">
    <tr><td class="l">Anträge wurden gestellt beim Jugendamt in</td><td>@foreach (['HOM', 'MZG', 'NK', 'SB', 'SLS', 'WND'] as $k){!! $x(in_array($k, $jugendaemter, true)) !!}{{ $k }}&nbsp;&nbsp; @endforeach</td></tr>
</table>
<table class="f">
    <tr><th class="kopf" colspan="4">Antragsteller/in (Verband/Gruppe)</th></tr>
    <tr><td colspan="4"><strong>{{ $as['name'] }}</strong></td></tr>
    <tr><td class="l">Anschrift</td><td colspan="3">{{ $as['anschrift'] }}</td></tr>
    <tr><td class="l">Bank</td><td>{{ $as['bank'] }}</td><td class="l">IBAN / BIC</td><td>{{ $as['iban'] }} {{ $as['bic'] ? '/ '.$as['bic'] : '' }}</td></tr>
    <tr><td class="l">Kontoinhaber/in</td><td colspan="3">{{ $as['inhaber'] }}</td></tr>
    <tr><td class="l">Rückfragen an</td><td>{{ $as['rueckfragen'] }}</td><td class="l">Tel. / E-Mail</td><td>{{ $as['telefon'] }} {{ $as['email'] ? '/ '.$as['email'] : '' }}</td></tr>
</table>

<table class="f">
    <tr><th class="kopf" colspan="4">Art der Maßnahme</th></tr>
    <tr><td colspan="4">{!! $x($event->zuschuss_art === 'schulung') !!} Mitarbeiter/innen-Schulung &nbsp; {!! $x($event->zuschuss_art === 'bildung') !!} Bildung &nbsp; {!! $x($event->zuschuss_art === 'freizeit') !!} Freizeit &nbsp; {!! $x($event->zuschuss_art === 'sonstige') !!} sonstige Maßnahme</td></tr>
    <tr><td class="l">Ort der Durchführung</td><td colspan="3">{{ $event->location }}</td></tr>
    <tr><td class="l">Beginn</td><td>{{ $event->starts_at->format('d.m.Y') }}, {{ $event->starts_at->format('H:i') }} Uhr</td><td class="l">Ende</td><td>{{ $ende->format('d.m.Y') }}, {{ $ende->format('H:i') }} Uhr</td></tr>
    <tr><td class="l">Maßnahme</td><td colspan="3">{{ $event->title }} ({{ count($event->days()) }} Tage)</td></tr>
</table>

<table class="f">
    <tr><th class="kopf">Zusammensetzung</th>@foreach ($kreise as $k)<th class="kopf z">{{ $k === 'SONST' ? 'Sonstige' : $k }}</th>@endforeach<th class="kopf z">Gesamt</th></tr>
    @foreach (['tn' => 'Kinder/Jugendliche/Junge Erwachsene', 'betreuung' => 'Betreuungspersonen', 'referenten' => 'Referenten/Referentinnen'] as $gruppe => $label)
        @continue($gruppe === 'referenten' && $event->zuschuss_art === 'freizeit')
        <tr><td>{{ $label }}</td>@foreach ($kreise as $k)<td class="z">{{ $zaehlung['kreise'][$k][$gruppe] ?: '' }}</td>@endforeach<td class="z"><strong>{{ $zaehlung['gesamt'][$gruppe] }}</strong></td></tr>
    @endforeach
    <tr><td><strong>Gesamt</strong></td>@foreach ($kreise as $k)<td class="z"><strong>{{ array_sum($zaehlung['kreise'][$k]) ?: '' }}</strong></td>@endforeach<td class="z"><strong>{{ array_sum($zaehlung['gesamt']) }}</strong></td></tr>
</table>

<table class="f">
    <tr><th class="kopf" colspan="2">Kosten</th><th class="kopf" colspan="2">Finanzierung</th></tr>
    <tr><td>Fahrt</td><td class="z">{{ $euro($f['fahrt'] ?? null) }}</td><td>Teilnehmerbeiträge</td><td class="z">{{ $euro($f['teilnahmebeitraege'] ?? null) }}</td></tr>
    <tr><td>Übernachtung (ggf. inkl. Halb- oder Vollpension)</td><td class="z">{{ $euro($f['unterkunft'] ?? null) }}</td><td colspan="2" class="klein">Sonstige Einnahmen (ohne Land, Kreise, Regionalverband):</td></tr>
    <tr><td>Verpflegung</td><td class="z">{{ $euro($f['verpflegung'] ?? null) }}</td><td>Spenden/Zuschüsse</td><td class="z">{{ $euro($f['spenden'] ?? null) }}</td></tr>
    <tr><td>Raummieten</td><td class="z">{{ $euro($f['raummiete'] ?? null) }}</td><td>Sonstige</td><td class="z">{{ $euro($f['sonstige'] ?? null) }}</td></tr>
    <tr><td>Arbeitsmaterialien</td><td class="z">{{ $euro($f['material'] ?? null) }}</td><td></td><td></td></tr>
    <tr><td>Durchführung, Vor- und Nachbereitung</td><td class="z">{{ $euro($f['durchfuehrung'] ?? null) }}</td><td></td><td></td></tr>
    <tr><td>Honorare</td><td class="z">{{ $euro($f['honorare'] ?? null) }}</td><td></td><td></td></tr>
    <tr><td><strong>Gesamtausgaben</strong></td><td class="z"><strong>{{ $euro($f['ausgaben']) }}</strong></td><td><strong>Gesamtmittel</strong></td><td class="z"><strong>{{ $euro($f['einnahmen']) }}</strong></td></tr>
    <tr><td colspan="2" class="z"><strong>Ungedeckte Kosten</strong></td><td colspan="2" class="z"><strong>{{ $euro($f['ungedeckt']) }}</strong></td></tr>
</table>

<p class="hinweis">Anlagen nach den jeweiligen Richtlinien. Mit der Einreichung des Antrages und Nachweises erklärt der Träger der Maßnahme auf der Grundlage des Kinder- und Jugendfördergesetzes vom 01.06.1994, den hierzu erlassenen Landesrichtlinien und/oder der Richtlinien der beteiligten Landkreise und des Regionalverbandes Saarbrücken die Richtigkeit und Vollständigkeit der vorstehenden Angaben und Unterlagen sowie die ordnungsgemäße, wirtschaftliche und zweckmäßige Verwendung der Zuschussmittel. Das Recht der beteiligten Zuschussstellen auf Nachprüfung wird anerkannt. Die Belege sind 5 Jahre aufzubewahren. Eine nicht ordnungsgemäße Verwendung des Zuschusses kann eine Rückforderung zur Folge haben.<br><strong>Mit der rechtsverbindlichen Unterschrift bestätigt der Antragsteller die Einhaltung der rechtlichen Grundlagen insbesondere der Bestimmungen des Bundeskinderschutzgesetzes (BKiSchG) bzgl. der beantragten Maßnahme.</strong></p>
<table class="sig"><tr><td><div class="linie">Ort, Datum</div></td><td><div class="linie">rechtsverbindliche Unterschrift</div></td></tr></table>
<p class="klein" style="margin-top:4mm">Erstellt mit Kluft nach dem Vordruck „Antrag und Nachweis“ (Formular A). Teilnahmeliste (Formular B) und Bericht (Formular D bzw. E) liegen bei.</p>
</body></html>
