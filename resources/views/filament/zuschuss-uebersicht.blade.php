{{-- Zuschuss: Zählung je Kreis, fehlende Angaben, Förderschätzung (Modal in „Teilnehmende“). --}}
@php($euro = fn (?int $c) => number_format(($c ?? 0) / 100, 2, ',', '.').' €')
<div class="space-y-6 text-sm">
    <section>
        <h3 class="font-semibold">Zusammensetzung (anwesende, bestätigte Personen)</h3>
        <table class="mt-2 w-full text-left">
            <thead><tr class="border-b"><th class="py-1">Kreis</th><th class="py-1 text-right">Teilnehmende</th><th class="py-1 text-right">Betreuung</th><th class="py-1 text-right">Referent*innen</th></tr></thead>
            <tbody>
                @foreach (\App\Support\Zuschuesse\Kreise::NAMEN as $kurz => $name)
                    @php($z = $zaehlung['kreise'][$kurz])
                    @continue(array_sum($z) === 0)
                    <tr class="border-b border-gray-100"><td class="py-1">{{ $kurz }} – {{ $name }}</td><td class="py-1 text-right">{{ $z['tn'] }}</td><td class="py-1 text-right">{{ $z['betreuung'] }}</td><td class="py-1 text-right">{{ $z['referenten'] }}</td></tr>
                @endforeach
                <tr class="font-semibold"><td class="py-1">Gesamt</td><td class="py-1 text-right">{{ $zaehlung['gesamt']['tn'] }}</td><td class="py-1 text-right">{{ $zaehlung['gesamt']['betreuung'] }}</td><td class="py-1 text-right">{{ $zaehlung['gesamt']['referenten'] }}</td></tr>
            </tbody>
        </table>
        <p class="mt-1 text-gray-500">Teilnehmende: {{ $zaehlung['geschlecht']['tn']['w'] }} w, {{ $zaehlung['geschlecht']['tn']['m'] }} m, {{ $zaehlung['geschlecht']['tn']['d'] }} d · Betreuung: {{ $zaehlung['geschlecht']['betreuung']['w'] }} w, {{ $zaehlung['geschlecht']['betreuung']['m'] }} m, {{ $zaehlung['geschlecht']['betreuung']['d'] }} d</p>
    </section>

    <section>
        <h3 class="font-semibold">Fehlende Angaben</h3>
        @forelse ($fehlend as $f)
            <p>{{ $f['name'] }}: {{ implode(', ', $f['fehlt']) }}</p>
        @empty
            <p class="text-gray-500">Alles da.</p>
        @endforelse
    </section>

    <section>
        <h3 class="font-semibold">Förderschätzung ({{ \App\Support\Zuschuesse::ARTEN[$event->zuschuss_art] ?? 'Art fehlt' }})</h3>
        @foreach ($schaetzung['geber'] as $g)
            <div class="mt-2">
                <p class="flex justify-between font-semibold"><span>{{ $g['name'] }}</span><span>{{ $euro($g['betrag']) }}</span></p>
                @foreach ($g['zeilen'] as $z)<p class="text-gray-600">{{ $z }}</p>@endforeach
                @foreach ($g['hinweise'] as $h)<p class="text-gray-500 italic">{{ $h }}</p>@endforeach
            </div>
        @endforeach
        <p class="mt-3 flex justify-between border-t pt-2 font-semibold"><span>Rechnerisch zusammen</span><span>{{ $euro($schaetzung['summe']) }}</span></p>
        @if ($schaetzung['ungedeckt'] !== null)
            <p class="flex justify-between"><span>Ungedeckte Kosten ({{ $euro($schaetzung['finanzen']['ausgaben']) }} − {{ $euro($schaetzung['finanzen']['einnahmen']) }})</span><span>{{ $euro($schaetzung['ungedeckt']) }}</span></p>
            <p class="flex justify-between font-semibold"><span>Höchstens zu erwarten</span><span>{{ $euro($schaetzung['hoechstens']) }}</span></p>
        @endif
        @foreach ($schaetzung['hinweise'] as $h)<p class="mt-1 text-gray-500 italic">{{ $h }}</p>@endforeach
        <p class="mt-2 text-xs text-gray-500">Schätzung ohne Gewähr nach den hinterlegten Richtlinien; über die Förderung entscheiden die Zuschussgeber.</p>
    </section>
</div>
