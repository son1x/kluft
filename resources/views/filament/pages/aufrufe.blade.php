{{-- Seitenaufrufe ohne Personenbezug (docs/aufrufe.md). --}}
<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="text-sm">
                <p>Gezählt werden Aufrufe ohne Bots und ohne angemeldete Redaktion. Es werden keine Daten über Besucher*innen gespeichert.</p>
                @php($seit = \App\Support\Aufrufe::seit())
                <p class="mt-1 font-medium">
                    @if ($seit)
                        Gezählt seit {{ $seit->format('d.m.Y') }}: {{ number_format(\App\Support\Aufrufe::gesamt(), 0, ',', '.') }} Aufrufe insgesamt.
                    @else
                        Noch keine Aufrufe gezählt (0).
                    @endif
                </p>
            </div>
            <label class="text-sm" style="min-width: 14rem">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="zeitraum" aria-label="Zeitraum">
                        @foreach (\App\Support\Aufrufe::ZEITRAEUME as $wert => $name)
                            <option value="{{ $wert }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>
    </x-filament::section>

    {{-- Verlauf und Summe; folgt dem Zeitraum (#[Reactive]). --}}
    @livewire(\App\Filament\Widgets\AufrufeVerlauf::class, ['zeitraum' => $zeitraum], key('aufrufe-verlauf'))

    {{ $this->table }}
</x-filament-panels::page>
