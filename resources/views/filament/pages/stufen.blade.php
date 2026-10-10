<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Die Altersangaben erscheinen auf der Website bei den Stufen, z. B. „Wölflinge (7 bis 10 Jahre)“. Standard sind die Angaben der DPSG;
        jede Grenze lässt sich um bis zu {{ \App\Support\StufenAlter::SPIELRAUM }} Jahr verschieben, wenn ihr die Stufen bei euch etwas anders aufteilt.
        Jede Änderung steht im Protokoll.
    </p>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-check">Speichern</x-filament::button>
    </form>
</x-filament-panels::page>
