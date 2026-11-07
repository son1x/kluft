{{-- Dashboard: Meistgelesen (30 Tage), Seitenaufrufe ohne Personenbezug. --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Meistgelesen (30 Tage)">
        @if ($eintraege->isEmpty())
            <p class="text-sm">Noch keine Aufrufe gezählt.</p>
        @else
            <ol style="list-style: decimal; padding-inline-start: 1.25rem" class="space-y-1 text-sm">
                @foreach ($eintraege as $eintrag)
                    <li>
                        <a href="{{ url($eintrag->pfad) }}" target="_blank" rel="noopener" style="text-decoration: underline">{{ $eintrag->titel }}</a>
                        <span style="opacity: .7">– {{ number_format($eintrag->aufrufe, 0, ',', '.') }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
        <p class="mt-3 text-sm"><a href="{{ $seite }}" style="text-decoration: underline">Alle Aufrufe ansehen</a></p>
    </x-filament::section>
</x-filament-widgets::widget>
