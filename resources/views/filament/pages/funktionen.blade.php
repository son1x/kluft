<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Hier schaltest du Funktionen für diese Website an oder aus. Abgeschaltete Funktionen verschwinden aus Website und Backend;
        bereits gespeicherte Daten bleiben erhalten und sind nach dem Wiedereinschalten wieder da. Jede Änderung steht im Protokoll.
    </p>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-check">Speichern</x-filament::button>
    </form>
</x-filament-panels::page>
