{{-- Formular C Aufenthaltsbestätigung (nur Landkreis Neunkirchen,
     Merzig-Wadern, Saarpfalz-Kreis), vorausgefüllt. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Formular C</title>@include('pdf.partials.zuschuss-stil')</head>
<body style="font-size: 11pt">
@php
    $b = (array) $event->zuschuss_bericht;
    $leitung = $betreuung->firstWhere('funktion', 'L') ?? $betreuung->first();
    $ende = $event->ends_at ?? $event->starts_at;
@endphp
<h1>Formular C – Aufenthaltsbestätigung</h1>
<p class="unter">(nur bei Anträgen beim Landkreis Neunkirchen, Merzig-Wadern und dem Saarpfalz-Kreis)</p>
<p style="margin-top: 10mm">Hiermit wird der Jugendgruppe <strong>{{ $as['name'] }}</strong></p>
<p style="margin-top: 6mm">bestätigt, dass sie unter Leitung von Gruppenleiter*in<br>
<strong>{{ $leitung ? $leitung['vorname'].' '.$leitung['nachname'] : '' }}</strong>{{ $leitung ? ', '.trim(($leitung['strasse'] ?? '').', '.($leitung['plz'] ?? '').' '.($leitung['wohnort'] ?? ''), ', ') : '' }}{{ $as['telefon'] ? ', Tel. '.$as['telefon'] : '' }}</p>
<p style="margin-top: 6mm">mit <strong>{{ array_sum($zaehlung['gesamt']) }}</strong> Personen vom <strong>{{ $event->starts_at->format('d.m.Y') }}</strong> bis <strong>{{ $ende->format('d.m.Y') }}</strong> in <strong>{{ $event->location }}</strong></p>
<p style="margin-top: 6mm">Unterkunft in <strong>{{ $b['unterkunft'] ?? '' }}</strong> bezogen hat.</p>
<table class="sig" style="margin-top: 30mm"><tr><td><div class="linie">Ort</div></td><td><div class="linie">Datum</div></td></tr></table>
<div style="margin-top: 25mm; border-top: 0.3mm solid #1c1f26; padding-top: 1mm"><strong>Stempel u. Unterschrift der Gemeinde / des Ortsvorstehers am Zeltlagerort.</strong> Beleg wird nur mit Stempel und im Original anerkannt!</div>
</body></html>
