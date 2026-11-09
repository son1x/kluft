{{-- Verwaltung → Fußzeile (App\Filament\Pages\Fusszeile). --}}
<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Die Fußzeile steht unten auf jeder Seite der Website. Ändere nur, was vom Standard abweichen soll; leere Felder übernehmen die Angaben dieser Website.
        Jede Änderung steht im Protokoll.
    </p>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-check">Speichern</x-filament::button>
    </form>
</x-filament-panels::page>
