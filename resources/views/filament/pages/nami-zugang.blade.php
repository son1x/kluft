<x-filament-panels::page>
    <x-filament::section heading="Verbindung">
        {{ $this->ampel() }}
        @if ($this->gespeichert())
            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button wire:click="test" color="gray" icon="heroicon-o-signal">Verbindung testen</x-filament::button>
            </div>
        @endif
        @if ($selbsttest)
            <p class="mt-3 text-sm" data-selbsttest>{{ $selbsttest }}</p>
        @endif
    </x-filament::section>

    <x-filament::section heading="Zugang speichern" description="Damit „Mitgliedschaft prüfen“ bei Veranstaltungen nicht jedes Mal nach dem Passwort fragt.">
        <div class="mb-4 space-y-2 text-sm text-gray-600 dark:text-gray-300">
            <p>Mitgliedsnummer und Passwort werden <strong>verschlüsselt</strong> gespeichert und nur für die NaMi-Anmeldung beim Mitgliederabgleich genutzt. Niemand im Backend kann sie lesen, auch die Administration nicht. Gespeichert wird nur, wenn die Anmeldung bei NaMi klappt.</p>
            <p>Das Ergebnis des Abgleichs folgt deinen NaMi-Rechten. Änderst du dein Passwort in NaMi, hier bitte neu eintragen. Wenn dir das Speichern nicht recht ist: einfach leer lassen, dann fragt der Abgleich jedes Mal.</p>
        </div>
        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}
            <div class="flex flex-wrap gap-2">
                <x-filament::button type="submit" icon="heroicon-o-lock-closed">Anmeldung testen und speichern</x-filament::button>
                @if ($this->gespeichert())
                    <x-filament::button wire:click="delete" wire:confirm="Gespeicherten NaMi-Zugang löschen?" color="danger" icon="heroicon-o-trash">Zugang löschen</x-filament::button>
                @endif
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
