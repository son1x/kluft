{{-- Feld „Stamm / Bezirk / Verband“: Autovervollständigung mit Stämmen und
     Bezirken von der Karte (Suche auch nach Ort und Bezirk), darunter
     „Gast aus einem anderen …“. Ohne JavaScript bleibt die Vorschlagsliste
     des Browsers; freie Eingabe ist immer möglich. --}}
@php($mitGast = isset($fields['herkunft']))
<div class="space-y-2" x-data="{ gast: @js(old('herkunft') === 'gast') }">
    <div>
        <label for="{{ $key }}" class="block text-sm font-bold">{{ $field['label'].($field['required'] ? ' *' : '') }}</label>
        <div class="relative" x-data="stammAutocomplete(@js($gruppierungen), @js(old($key, '')))" @click.outside="open = false">
            <input id="{{ $key }}" name="{{ $key }}" type="text" list="gruppierungen" autocomplete="off" maxlength="150" @required($field['required']) value="{{ old($key) }}" class="{{ $input }}"
                   x-init="$el.removeAttribute('list')" x-model="query"
                   role="combobox" aria-autocomplete="list" aria-controls="{{ $key }}-vorschlaege" :aria-expanded="open.toString()"
                   :aria-activedescendant="active >= 0 ? '{{ $key }}-vorschlag-' + active : null"
                   @input="open = true; active = -1" @focus="open = query.length > 0"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                   @keydown.enter="if (open && active >= 0) { $event.preventDefault(); choose(results[active]) }"
                   @keydown.escape="open = false" />
            <ul id="{{ $key }}-vorschlaege" role="listbox" x-show="open && results.length" x-cloak
                class="absolute z-20 mt-1 max-h-72 w-full overflow-auto border border-dpsg-mittelbeige bg-white shadow-lg">
                <template x-for="(option, i) in results" :key="option.value">
                    <li :id="'{{ $key }}-vorschlag-' + i" role="option" :aria-selected="(i === active).toString()"
                        @mousedown.prevent="choose(option)" @mouseenter="active = i"
                        :class="i === active ? 'bg-dpsg-blau text-white' : ''" class="cursor-pointer px-3 py-2">
                        <span class="block font-bold" x-text="option.value"></span>
                        <span class="block text-xs" :class="i === active ? 'text-white/80' : 'text-text-secondary'" x-text="option.hint"></span>
                    </li>
                </template>
            </ul>
            <datalist id="gruppierungen">@foreach ($gruppierungen as $option)<option value="{{ $option['value'] }}">{{ $option['hint'] }}</option>@endforeach</datalist>
        </div>
        <p class="mt-1 text-xs text-text-secondary" x-show="! gast">Tippe den Namen deines Stamms oder Bezirks oder den Ort – oder trag etwas anderes ein.</p>
        @if ($mitGast)
            <p class="mt-1 text-xs text-text-secondary" x-show="gast" x-cloak>Bitte Diözesanverband bzw. Verband und Gruppe eintragen, z. B. „DV Köln, Stamm Sankt Georg“ oder „VCP Saarbrücken“.</p>
        @endif
        @error($key)<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
    </div>

    @if ($mitGast)
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="herkunft" value="gast" x-model="gast" @checked(old('herkunft') === 'gast') /> {{ $fields['herkunft']['options']['gast'] }}</label>
    @endif
</div>
