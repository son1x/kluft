<x-filament-panels::page>
    <x-filament::section heading="Liste der jugendlichen Mitglieder" description="Die Stadt Saarlouis zahlt jährlich einen Pro-Kopf-Zuschuss je Mitglied unter 18 (Stichtag 01.01., Antrag bis 15.02.). Kluft liest den Mitglieder-Export aus NaMi, nimmt aktive Mitglieder unter 18 auf (keine Schnuppermitglieder) und erzeugt die namentliche Liste mit Anschrift, Geburtsdatum und Feldern für die Richtigkeitsbestätigung. Die hochgeladene Datei wird danach verworfen; Kluft speichert nichts davon.">
        <form wire:submit="erzeugen" style="display: grid; gap: 1rem; justify-items: start;">
            {{ $this->form }}
            <x-filament::button type="submit" icon="heroicon-o-document-arrow-down">PDF erzeugen</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
