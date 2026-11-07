{{-- Titelbilder aus Seite 1 der PDFs erzeugen (resources/js/titelbild.js). --}}
<div
    x-data="titelbilder(@js(['ausgaben' => $ausgaben, 'ueberschreiben' => $ueberschreiben, 'automatisch' => $automatisch ?? false]))"
    class="space-y-4 text-sm"
>
    <div x-show="! laeuft && ! fertig" class="space-y-3">
        <p>
            @if (count($ausgaben) === 1)
                Kluft öffnet das PDF in diesem Browser und macht aus Seite 1 das Titelbild.
            @else
                {{ count($ausgaben) }} Georgsboten ohne Titelbild. Kluft öffnet die PDFs nacheinander in diesem Browser und macht aus Seite 1 jeweils das Titelbild.
            @endif
            Das Fenster bitte offen lassen, bis alles fertig ist.
        </p>
        <x-filament::button type="button" x-on:click="start()" icon="heroicon-o-photo">
            {{ $ueberschreiben ? 'Titelbild ersetzen' : 'Jetzt erzeugen' }}
        </x-filament::button>
    </div>

    <div x-show="laeuft" x-cloak class="space-y-2" role="status" aria-live="polite">
        <p class="font-medium" x-text="fortschritt"></p>
        <progress max="100" x-bind:value="prozent" style="width: 100%" aria-label="Fortschritt"></progress>
    </div>

    <div x-show="fertig" x-cloak class="space-y-2" role="status" aria-live="polite">
        <p class="font-medium" x-text="ergebnis"></p>
        <ul x-show="fehler.length" style="list-style: disc; padding-inline-start: 1.25rem; color: var(--danger-600)">
            <template x-for="f in fehler" x-bind:key="f.label">
                <li><span x-text="f.label"></span>: <span x-text="f.grund"></span></li>
            </template>
        </ul>
    </div>
</div>
