{{-- Formular D (Bericht über Freizeitmaßnahme) bzw. E (Bericht über
     Mitarbeiterschulung/Bildungsmaßnahme), Saarland, Nachbau. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Bericht</title>@include('pdf.partials.zuschuss-stil')</head>
<body>
@php
    $b = (array) $event->zuschuss_bericht;
    $ende = $event->ends_at ?? $event->starts_at;
    $leitung = $betreuung->whereIn('funktion', ['L'])->values();
    $leitung = $leitung->isNotEmpty() ? $leitung : $betreuung->take(1);
    $x = fn (bool $an) => '<span class="box">'.($an ? 'X' : '&nbsp;').'</span>';
@endphp
@if ($event->zuschuss_art === 'freizeit')
    <h1>Formular D – Bericht über Freizeitmaßnahme</h1>
    <table class="f">
        <tr><td class="l">Freizeitmaßnahme</td><td colspan="3">{{ $event->title }}</td></tr>
        <tr><td class="l">vom</td><td>{{ $event->starts_at->format('d.m.Y') }}</td><td class="l">bis</td><td>{{ $ende->format('d.m.Y') }}</td></tr>
        <tr><td class="l">Ort der Veranstaltung</td><td colspan="3">{{ $event->location }}</td></tr>
    </table>
@else
    <h1>Formular E – Bericht über {!! $x($event->zuschuss_art === 'schulung') !!} Mitarbeiterschulung {!! $x($event->zuschuss_art !== 'schulung') !!} Bildungsmaßnahme</h1>
    <table class="f">
        <tr><td class="l">am/vom</td><td>{{ $event->starts_at->format('d.m.Y') }}</td><td class="l">bis</td><td>{{ $ende->format('d.m.Y') }}</td></tr>
        <tr><td class="l">in</td><td colspan="3">{{ $event->location }}</td></tr>
        <tr><td class="l">Thema</td><td colspan="3">{{ $b['thema'] ?? $event->title }}</td></tr>
    </table>
@endif

<table class="f">
    <tr><th class="kopf" colspan="2">{{ $event->zuschuss_art === 'freizeit' ? 'Leiter/in der Maßnahme (Name, Qualifikation)' : 'Verantwortliche/r Leiter/in (Name/Qualifikation)' }}</th></tr>
    @forelse ($leitung as $p)
        <tr><td>{{ $p['vorname'] }} {{ $p['nachname'] }}</td><td>{!! $x($p['juleica']) !!} Juleica-Inhaber/in &nbsp; {!! $x(! $p['juleica']) !!} langjährige Tätigkeit in der Jugendarbeit</td></tr>
    @empty
        <tr><td colspan="2">&nbsp;</td></tr>
    @endforelse
</table>

@if ($event->zuschuss_art !== 'freizeit')
    <table class="f">
        <tr><th class="kopf">Inhalt/Methodik / Zielsetzung der Maßnahme</th></tr>
        <tr><td style="height: 22mm">{!! nl2br(e($b['methodik'] ?? '')) !!}</td></tr>
    </table>
@endif

<table class="f">
    <tr><th class="kopf" colspan="3">{{ $event->zuschuss_art === 'freizeit' ? 'Betreuer/innen (Name und Qualifikation)' : 'Referent/innen u. Betreuer/innen (Name und Qualifikation)' }}</th></tr>
    @foreach ($betreuung->reject(fn ($p) => $leitung->contains('id', $p['id']))->values() as $i => $p)
        <tr><td style="width:8mm">{{ $i + 1 }}.</td><td>{{ $p['vorname'] }} {{ $p['nachname'] }}{{ $p['funktion'] === 'R' ? ' (Referent*in)' : '' }}</td><td>{{ \App\Support\Zuschuesse::qualifikation($p) }}</td></tr>
    @endforeach
</table>

<table class="f">
    <tr><th class="kopf" colspan="3">{{ $event->zuschuss_art === 'freizeit' ? 'Programmablauf (stichwortartig)' : 'Verlauf der Maßnahme (Programm mit Themen, Referenten und Zeitangaben)' }}</th></tr>
    @forelse ($programm as $punkt)
        <tr><td style="width:38mm">{{ $punkt['zeit'] }}</td><td>{{ $punkt['thema'] }}</td><td style="width:40mm">{{ $punkt['referent'] }}</td></tr>
    @empty
        <tr><td colspan="3" style="height: 30mm">&nbsp;</td></tr>
    @endforelse
</table>

<table class="f">
    <tr><th class="kopf">{{ $event->zuschuss_art === 'freizeit' ? 'Bericht' : 'Ergebnis der Bewertung der Maßnahme' }}</th></tr>
    <tr><td style="height: 18mm">
        @if (filled($b['bericht'] ?? null)){!! nl2br(e($b['bericht'])) !!}@else{!! $x(true) !!} wurde programmgemäß durchgeführt @endif
    </td></tr>
</table>
<table class="sig"><tr><td><div class="linie">Datum, Unterschrift Antragsteller bzw. Verantwortliche/r</div></td><td></td></tr></table>
</body></html>
