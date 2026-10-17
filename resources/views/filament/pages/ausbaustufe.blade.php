<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Startet klein und schaltet mehr frei, wenn ihr so weit seid. Jede Stufe enthält die vorherigen. Kluft läuft immer vollständig im Hintergrund:
        Was ihr in einer höheren Stufe angelegt habt, bleibt beim Zurückschalten gespeichert und ist nur auf der Website ausgeblendet. Jede Änderung steht im Protokoll.
    </p>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-check">Speichern</x-filament::button>
    </form>
</x-filament-panels::page>
