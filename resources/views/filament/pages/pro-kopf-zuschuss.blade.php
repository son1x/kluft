<x-filament-panels::page>
    @if ($fortschritt)
        {{-- NaMi-Abruf läuft (ProKopfNami): die Seite fragt selbst weiter; die Knöpfe sind der Rückfall. --}}
        <x-filament::section heading="Abruf aus NaMi läuft" icon="heroicon-o-arrow-path">
            <p style="font-size: .875rem;" role="status" aria-live="polite">{{ $fortschritt }}</p>
            <div style="margin-top: .75rem; display: flex; gap: .5rem;">
                <x-filament::button wire:click="weiter" wire:loading.attr="disabled" size="sm">Weiter</x-filament::button>
                <x-filament::button wire:click="abbrechen" color="gray" size="sm">Abbrechen</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @php($mitNami = config('mitglieder.driver') === 'nami')
    <x-filament::section heading="Liste der jugendlichen Mitglieder" :description="'Die Stadt Saarlouis zahlt jährlich einen Pro-Kopf-Zuschuss je Mitglied unter 18 (Stichtag 01.01., Antrag bis 15.02.). Kluft nimmt aktive Mitglieder unter 18 auf (keine Schnuppermitglieder) und erzeugt die namentliche Liste mit Anschrift, Geburtsdatum und Feldern für die Richtigkeitsbestätigung. '.($mitNami ? 'Am einfachsten oben mit „Aus NaMi abrufen“; sonst hier den Mitglieder-Export aus NaMi hochladen.' : 'Dazu den Mitglieder-Export aus NaMi hochladen.').' Kluft speichert nichts davon.'">
        <form wire:submit="erzeugen" style="display: grid; gap: 1rem; justify-items: start;">
            {{ $this->form }}
            <x-filament::button type="submit" icon="heroicon-o-document-arrow-down">PDF aus Datei erzeugen</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
