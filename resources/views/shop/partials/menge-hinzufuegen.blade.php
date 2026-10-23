{{-- Anzahl (− n +) und „Hinzufügen“ für eine Zeile der Bestellliste.
     Erwartet: $ziel (Alpine-Objekt mit „menge“), $aktion (Alpine-Aufruf), $schluessel (für Hinweise) --}}
<div class="flex items-center gap-2">
    <div class="inline-flex items-center border border-dpsg-mittelbeige bg-white" role="group" aria-label="Anzahl">
        <button type="button" class="h-10 w-9 text-lg" @click="{{ $ziel }}.menge = Math.max(1, (parseInt({{ $ziel }}.menge) || 1) - 1)" aria-label="weniger">−</button>
        <input type="number" min="1" max="10" x-model.number="{{ $ziel }}.menge" class="h-10 w-10 border-x border-dpsg-mittelbeige text-center [appearance:textfield]" aria-label="Anzahl" />
        <button type="button" class="h-10 w-9 text-lg" @click="{{ $ziel }}.menge = Math.min(10, (parseInt({{ $ziel }}.menge) || 0) + 1)" aria-label="mehr">+</button>
    </div>
    <button type="button" @click="{{ $aktion }}" class="h-10 bg-dpsg-blau px-4 text-sm font-bold text-white hover:bg-dpsg-rot">Hinzufügen</button>
</div>
<p class="w-full text-sm" x-show="hinweis[@js($schluessel)]" x-text="hinweis[@js($schluessel)]?.text" :class="hinweis[@js($schluessel)]?.fehler ? 'text-dpsg-rot' : 'text-stufe-pfadi'" role="status"></p>
