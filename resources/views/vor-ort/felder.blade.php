{{-- Eingabefelder der Anmeldung (Vor Ort): $m = Name des Alpine-Objekts mit .werte, $pflicht = Pflichtfelder erzwingen. --}}
    <template x-for="f in v.felder" x-bind:key="f.schluessel">
        <div>
            <p class="text-sm font-semibold"><span x-text="f.label"></span><span x-show="f.sensibel" class="ml-1 text-xs text-dpsg-rot">(sensibel)</span>@if($pflicht)<span x-show="f.pflicht" class="ml-1 text-xs text-gray-600">(Pflicht)</span>@endif</p>
            <template x-if="['days', 'mehrfach'].includes(f.typ)">
                <div class="mt-1 flex flex-wrap gap-2">
                    <template x-for="[k, l] in Object.entries(f.optionen || {})" x-bind:key="k">
                        <label class="flex min-h-11 items-center gap-2 rounded-lg border border-dpsg-mittelbeige px-3">
                            <input type="checkbox" class="size-5" x-bind:value="k" x-model="{{ $m }}.werte[f.schluessel]"><span x-text="l"></span>
                        </label>
                    </template>
                </div>
            </template>
            <template x-if="['select', 'herkunft'].includes(f.typ)">
                <select x-model="{{ $m }}.werte[f.schluessel]"@if($pflicht) x-bind:required="f.pflicht"@endif class="mt-1 min-h-11 w-full rounded-lg border border-dpsg-mittelbeige bg-white px-3">
                    <option value="">– keine Angabe –</option>
                    <template x-for="[k, l] in Object.entries(f.optionen || {})" x-bind:key="k">
                        <option x-bind:value="k" x-text="l" x-bind:selected="{{ $m }}.werte[f.schluessel] === k"></option>
                    </template>
                </select>
            </template>
            <template x-if="f.typ === 'textarea'">
                <textarea x-model="{{ $m }}.werte[f.schluessel]"@if($pflicht) x-bind:required="f.pflicht"@endif rows="3" maxlength="1000" class="mt-1 w-full rounded-lg border border-dpsg-mittelbeige px-3 py-2"></textarea>
            </template>
            <template x-if="!['days', 'mehrfach', 'select', 'herkunft', 'textarea'].includes(f.typ)">
                <input x-bind:type="['year', 'zahl'].includes(f.typ) ? 'number' : (f.typ === 'date' ? 'date' : 'text')" x-model="{{ $m }}.werte[f.schluessel]"@if($pflicht) x-bind:required="f.pflicht"@endif maxlength="1000"
                       class="mt-1 min-h-11 w-full rounded-lg border border-dpsg-mittelbeige px-3">
            </template>
        </div>
    </template>
