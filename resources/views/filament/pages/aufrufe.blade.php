{{-- Seitenaufrufe ohne Personenbezug (docs/aufrufe.md). --}}
<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <p class="text-sm">
                Gezählt werden Aufrufe ohne Bots und ohne angemeldete Redaktion. Es werden keine Daten über Besucher*innen gespeichert.
            </p>
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
