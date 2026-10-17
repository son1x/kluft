<x-filament-panels::page>
    <x-filament::section heading="Abfrage" description="Fragt NaMi so ab, wie es die Kasse und der Mitgliederabgleich tun, und zeigt nur die Form der Antworten.">
        <div class="mb-4 space-y-2 text-sm text-gray-600 dark:text-gray-300">
            <p>Namen, Nummern, Adressen und Daten erscheinen nur als Muster (z. B. „Aaaa“ oder „9999-99-99“). Lesbar bleiben Gruppierungen, Beitragsarten und Merkmale wie Mitgliedstyp oder Status – als Anzahlen und erst ab zehn Personen. Das Ergebnis wird nicht gespeichert.</p>
            @unless ($this->mitEingabe())
                <p>Angemeldet wird mit deinem im Profil gespeicherten NaMi-Zugang.</p>
            @endunless
        </div>
        <form wire:submit="abfragen" class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit" icon="heroicon-o-play">Testabfrage starten</x-filament::button>
        </form>
    </x-filament::section>

    @if ($bericht)
        <x-filament::section heading="Ergebnis" description="Bitte vor dem Weitergeben kurz durchsehen.">
            <div x-data="{ kopiert: false }" class="space-y-3">
                <x-filament::button color="gray" icon="heroicon-o-clipboard-document"
                    x-on:click="navigator.clipboard.writeText($refs.bericht.innerText); kopiert = true; setTimeout(() => kopiert = false, 2000)">
                    <span x-show="! kopiert">Ergebnis kopieren</span><span x-show="kopiert" x-cloak>Kopiert</span>
                </x-filament::button>
                <pre x-ref="bericht" class="max-h-96 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-3 text-xs dark:bg-gray-900" style="max-height:32rem;overflow-wrap:anywhere" data-bericht>{{ $bericht }}</pre>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
