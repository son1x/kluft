{{-- Fehlende Angaben einer Person nachtragen (Vor Ort). $p = Alpine-Ausdruck der Person, $knopf = Knopf „Nachtragen“ zeigen. --}}
<div x-show="fehltDefs({{ $p }}).length" class="rounded-xl border-2 border-dpsg-yellow bg-dpsg-hellbeige p-3">
    <p class="font-semibold text-dpsg-blau">Es fehlen noch Angaben <span class="text-xs font-normal text-gray-700">(für die Teilnahmeliste)</span></p>
    <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <template x-for="f in fehltDefs({{ $p }})" x-bind:key="f.schluessel">
            <label class="block"><span class="text-sm font-semibold" x-text="f.label"></span>
                <input x-bind:type="f.typ === 'date' ? 'date' : (f.typ === 'year' ? 'number' : 'text')" x-model="fehltEingabe[f.schluessel]"
                       x-bind:inputmode="f.typ === 'plz' || f.typ === 'year' ? 'numeric' : 'text'" maxlength="100" autocomplete="off"
                       class="mt-1 min-h-11 w-full rounded-lg border border-dpsg-mittelbeige bg-white px-3"></label>
        </template>
    </div>
    @if ($knopf)
        <button type="button" x-on:click="fehltSpeichern({{ $p }})" class="mt-3 min-h-11 rounded-lg bg-dpsg-blau px-4 font-semibold text-white">Nachtragen</button>
    @else
        <p class="mt-2 text-xs text-gray-700">Wird beim Speichern der Unterschrift mit eingetragen. Leer lassen, wenn es später nachgetragen wird.</p>
    @endif
</div>
