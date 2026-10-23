{{-- Ergebnis einer Frage als Balken (nur HTML und Inline-Styles, damit es
     im Backend und im PDF gleich aussieht). Erwartet: $e (Auswertung::frage) --}}
@php($f = $e['frage'])
@if ($f->typ === 'abschnitt')
    <h2 style="margin: 1.5rem 0 .25rem; font-size: 1.15rem; font-weight: bold;">{{ $f->text }}</h2>
@else
    <div style="margin: 0 0 1.25rem; page-break-inside: avoid;">
        <p style="margin: 0; font-weight: bold;">{{ $f->text }}</p>
        <p style="margin: .1rem 0 .4rem; font-size: .8rem; color: #6b7280;">
            {{ $e['beantwortet'] }} {{ $e['beantwortet'] === 1 ? 'Antwort' : 'Antworten' }}@if ($f->bedingung && filled($f->bedingung['frage'] ?? null)) · nur bei passender Vorantwort gezeigt ({{ $e['gezeigt'] }}×)@endif
            @if ($e['mittel'] !== null) · <strong style="color: #003056;">Mittelwert {{ number_format($e['mittel'], 1, ',', '') }}</strong> ({{ $e['mittel_hinweis'] }})@endif
        </p>
        @if ($e['verteilung'] !== null)
            @php($summe = max(1, $f->typ === 'mehrfach' ? $e['beantwortet'] : array_sum($e['verteilung'])))
            <table style="width: 100%; border-collapse: collapse; font-size: .85rem;">
                @foreach ($e['verteilung'] as $label => $n)
                    @php($prozent = round($n / $summe * 100))
                    <tr>
                        <td style="width: 32%; padding: .15rem .5rem .15rem 0; vertical-align: middle;">{{ $label }}</td>
                        <td style="padding: .15rem 0; vertical-align: middle;">
                            <div style="background: #e5e7eb; height: .9rem; width: 100%;"><div style="background: #003056; height: .9rem; width: {{ $prozent }}%;"></div></div>
                        </td>
                        <td style="width: 16%; padding: .15rem 0 .15rem .5rem; text-align: right; white-space: nowrap; vertical-align: middle;">{{ $n }} · {{ $prozent }} %</td>
                    </tr>
                @endforeach
            </table>
            @if ($f->typ === 'mehrfach')<p style="margin: .2rem 0 0; font-size: .75rem; color: #6b7280;">Mehrfachnennung: Prozent bezogen auf {{ $e['beantwortet'] }} Antwortende.</p>@endif
        @endif
        @foreach ([['Antworten', $e['texte']], ['Sonstiges', $e['sonstiges']], ['Kommentare', $e['kommentare']]] as [$titel, $liste])
            @if ($liste !== [])
                <p style="margin: .5rem 0 .2rem; font-size: .8rem; font-weight: bold;">{{ $titel }} ({{ count($liste) }})</p>
                <ul style="margin: 0; padding-left: 1.1rem; font-size: .85rem;">
                    @foreach ($liste as $text)<li style="margin-bottom: .2rem;">{!! nl2br(e($text)) !!}</li>@endforeach
                </ul>
            @endif
        @endforeach
    </div>
@endif
