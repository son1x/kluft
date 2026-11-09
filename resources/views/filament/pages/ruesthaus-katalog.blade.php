{{-- Sammelbestellung → Rüsthaus-Katalog (App\Filament\Pages\RuesthausKatalog).
     Liste mit eigenem CSS (rk-…), weil das Backend nur Filaments eigene Klassen ausliefert. --}}
<x-filament-panels::page>
    <style>
        .rk-raster { display: grid; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); gap: .75rem; }
        .rk-karte { position: relative; display: flex; flex-direction: column; gap: .5rem; padding: .6rem; border-radius: .9rem; background: #fff; border: 1px solid rgb(0 0 0 / .08); }
        .dark .rk-karte { background: rgb(255 255 255 / .05); border-color: rgb(255 255 255 / .1); }
        .rk-karte:has(.rk-wahl input:checked) { outline: 2px solid var(--primary-600); outline-offset: -1px; }
        .rk-bild { aspect-ratio: 1; border-radius: .6rem; background: #f3f4f6; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .dark .rk-bild { background: rgb(255 255 255 / .06); }
        .rk-bild img { width: 100%; height: 100%; object-fit: contain; }
        .rk-name { font-size: .875rem; font-weight: 600; line-height: 1.3; }
        .rk-klein { font-size: .8rem; color: var(--gray-500); }
        .rk-status { position: absolute; top: 1rem; left: 1rem; padding: .1rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .15); }
        .rk-status.neu { color: var(--primary-700); }
        .rk-status.gesperrt { color: var(--danger-700); }
        .rk-status.im-shop, .rk-status.entwurf { color: var(--gray-600); }
        .rk-wahl { display: flex; align-items: center; gap: .5rem; min-height: 2.75rem; font-size: .875rem; cursor: pointer; }
        .rk-wahl input { width: 1.15rem; height: 1.15rem; }
        .rk-leiste { position: sticky; bottom: 1rem; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; padding: .75rem 1rem; border-radius: 1rem; background: rgb(255 255 255 / .9); backdrop-filter: blur(12px); box-shadow: 0 6px 24px rgb(0 0 0 / .12); }
        .dark .rk-leiste { background: rgb(24 24 27 / .9); }
        .rk-hinweis { padding: .75rem 1rem; border-radius: .75rem; font-size: .875rem; background: var(--gray-50); border: 1px solid var(--gray-200); }
        .dark .rk-hinweis { background: rgb(255 255 255 / .05); border-color: rgb(255 255 255 / .1); }
        .rk-hinweis.fehler { background: var(--danger-50); border-color: var(--danger-200); color: var(--danger-800); }
    </style>

    @php($stand = $this->stand())
    @if ($stand && $stand['laeuft'])
        <p class="rk-hinweis" role="status">Übernahme läuft: {{ $stand['erledigt'] }} von {{ $stand['gesamt'] }} erledigt. Den Stand siehst du auch unter „Artikel“.</p>
    @endif

    {{ $this->form }}

    @if (filled($this->data['haupt'] ?? null))
        <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
            <x-filament::button wire:click="laden" icon="heroicon-o-arrow-down-tray">Artikel anzeigen</x-filament::button>
            @if ($this->artikel !== [])
                <x-filament::button wire:click="laden(true)" color="gray" icon="heroicon-o-arrow-path">Neu vom Rüsthaus laden</x-filament::button>
            @endif
        </div>
    @endif

    <div wire:loading.delay wire:target="laden" class="rk-hinweis" role="status">Lade die Übersichtsseiten des Rüsthaus … (etwa eine Sekunde je 48 Artikel)</div>

    @if ($this->fehler)
        <p class="rk-hinweis fehler" role="alert">{{ $this->fehler }}</p>
    @endif

    @if ($this->artikel !== [])
        @php($zeilen = $this->zeilen())
        <p class="rk-klein">
            {{ count($this->artikel) }} Artikel in der Kategorie{{ $this->unvollstaendig ? ' (nur die ersten 480, für mehr eine Unterkategorie wählen)' : '' }}, angezeigt {{ count($zeilen) }}.
            Schon übernommene und gesperrte Artikel lassen sich nicht noch einmal auswählen.
            „ab“ heißt: andere Größen kosten mehr, oder das Rüsthaus hat einen Staffelpreis für große Mengen. Übernommen wird der Einzelpreis je Größe.
        </p>

        <div class="rk-raster">
            @forelse ($zeilen as $z)
                <div wire:key="rk-{{ md5($z['url']) }}" class="rk-karte">
                    <span @class(['rk-status', str($z['status'])->slug()->toString()])>{{ $z['status'] }}</span>
                    <div class="rk-bild">
                        @if ($z['bild'])
                            <img src="{{ $z['bild'] }}" alt="" loading="lazy" width="200" height="200">
                        @endif
                    </div>
                    <div class="rk-name">{{ $z['name'] }}</div>
                    <div class="rk-klein">{{ $z['preis'] }} · Nr. {{ $z['nummer'] }} · <a href="{{ $z['url'] }}" target="_blank" rel="noopener" style="text-decoration: underline;">ansehen</a></div>
                    @if ($z['waehlbar'])
                        <label class="rk-wahl"><input type="checkbox" wire:model="auswahl" value="{{ $z['url'] }}"> Übernehmen</label>
                    @endif
                </div>
            @empty
                <p class="rk-klein">Keine Artikel für diese Auswahl. „Nur neue Artikel zeigen“ ausschalten oder die Suche ändern.</p>
            @endforelse
        </div>

        <div class="rk-leiste">
            {{-- Auswahl bleibt im Browser, bis „übernehmen“ sie schickt (keine Anfrage je Haken). --}}
            <strong x-data x-text="$wire.auswahl.length + ' ausgewählt'">{{ count($this->auswahl) }} ausgewählt</strong>
            <x-filament::button size="sm" color="gray" wire:click="alleNeuenWaehlen">Alle neuen auswählen</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="keineWaehlen">Auswahl leeren</x-filament::button>
            <span style="flex: 1;"></span>
            <x-filament::button wire:click="uebernehmen" icon="heroicon-o-check">Ausgewählte übernehmen</x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
