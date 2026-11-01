{{-- Maske „Zuschussantrag“: Stand je Zuschussgeber und was den Unterlagen noch fehlt. --}}
@php($euro = fn (?int $c) => $c === null ? '–' : number_format($c / 100, 2, ',', '.').' €')
<x-filament::section heading="Stand und Unterlagen" description="Aktualisiert sich nach dem Speichern. Die Unterlagen (Knopf „Unterlagen“ oben) übernehmen alle Angaben dieser Maske.">
    @if (! $event->zuschussAktiv())
        <p class="text-sm">„Zuschuss beantragen“ ist für diese Veranstaltung aus. Einschalten und speichern, dann gibt es Unterlagen und Fristen.</p>
    @else
        <div class="space-y-4 text-sm">
            <table class="w-full text-left">
                <thead><tr class="border-b"><th class="py-1">Zuschussgeber</th><th class="py-1">Frist</th><th class="py-1">Stand</th><th class="py-1 text-right">Schätzung</th><th class="py-1 text-right">Beantragt</th><th class="py-1 text-right">Bewilligt</th></tr></thead>
                <tbody>
                    @foreach ($uebersicht as $z)
                        @php($ueberfaellig = $z['status'] === 'offen' && $z['frist']->isPast())
                        <tr class="border-b border-gray-100">
                            <td class="py-1">{{ $z['name'] }}</td>
                            <td class="py-1" @if ($ueberfaellig) style="color: rgb(220 38 38)" @endif>{{ $z['frist']->format('d.m.Y') }}{{ $ueberfaellig ? ' (verstrichen)' : '' }}</td>
                            <td class="py-1">{{ \App\Support\Zuschuesse\Antrag::STATUS[$z['status']] }}</td>
                            <td class="py-1 text-right">{{ $euro($z['geschaetzt']) }}</td>
                            <td class="py-1 text-right">{{ $euro($z['beantragt']) }}</td>
                            <td class="py-1 text-right">{{ $euro($z['bewilligt']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div>
                <p class="font-semibold">Was den Unterlagen noch fehlt</p>
                @php($alles = collect($fehlend)->every(fn ($f) => $f === []))
                @if ($alles)
                    <p class="text-gray-500">Nichts – alle Unterlagen sind vollständig.</p>
                @else
                    @foreach ($fehlend as $art => $liste)
                        @continue($liste === [])
                        <p><span class="font-medium">{{ \App\Services\Zuschuss\ZuschussPdf::UNTERLAGEN[$art][0] }}:</span> {{ implode(', ', $liste) }}</p>
                    @endforeach
                @endif
            </div>
        </div>
    @endif
</x-filament::section>
