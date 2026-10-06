{{-- Stamm und (wo es Bezirke gibt) Bezirk, darunter „Gast aus einem anderen …“.
     Bezirk: Auswahl; die Stamm-Vorschläge passen zum gewählten Bezirk, die
     Wahl eines Stamms setzt den Bezirk. Gäste tragen Diözesanverband/Verband
     und Gruppe frei ein. Ohne JavaScript: Auswahl und Vorschlagsliste des
     Browsers, freie Eingabe ist immer möglich. --}}
@php($mitBezirk = isset($fields['bezirk']))
@php($mitGast = isset($fields['herkunft']))
@php($stern = $field['required'] ? ' *' : '')
<div class="space-y-4" x-data="{ gast: @js(old('herkunft') === 'gast'), bezirk: @js((string) old('bezirk', '')) }">
    @if ($mitBezirk)
        <div>
            <label for="bezirk" class="block text-sm font-bold"><span x-show="! gast">Bezirk</span><span x-show="gast" x-cloak>Diözesanverband / Verband</span>{{ $stern }}</label>
            <select id="bezirk" name="bezirk" x-show="! gast" :disabled="gast" x-model="bezirk" @required($field['required']) class="{{ $input }}">
                <option value="">Bitte wählen</option>
                @foreach ($bezirke as $name)
                    <option value="{{ $name }}" @selected(old('bezirk') === $name)>Bezirk {{ $name }}</option>
                @endforeach
            </select>
            <input id="bezirk-gast" name="bezirk" type="text" maxlength="150" x-show="gast" x-cloak disabled :disabled="! gast" @if ($field['required']) :required="gast" @endif
                   value="{{ old('herkunft') === 'gast' ? old('bezirk') : '' }}" placeholder="z. B. DV Köln oder VCP" class="{{ $input }}" />
        </div>
    @endif

    <div>
        <label for="{{ $key }}" class="block text-sm font-bold">
            <span x-show="! gast">{{ $mitBezirk ? 'Stamm' : $field['label'] }}</span><span x-show="gast" x-cloak>{{ $mitBezirk ? 'Stamm / Gruppe' : 'Verband und Gruppe' }}</span>{{ $stern }}
        </label>
        <div class="relative" x-data="stammAutocomplete(@js($gruppierungen), @js(old($key, '')), @js($mitBezirk))" @click.outside="open = false">
            <input id="{{ $key }}" name="{{ $key }}" type="text" list="gruppierungen" autocomplete="off" @required($field['required']) value="{{ old($key) }}" class="{{ $input }}"
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
        <p class="mt-1 text-xs text-text-secondary" x-show="! gast">Tippe den Namen oder den Ort{{ $mitBezirk ? '' : ' oder den Bezirk' }} – oder trag etwas anderes ein.</p>
        <p class="mt-1 text-xs text-text-secondary" x-show="gast" x-cloak>{{ $mitBezirk ? 'z. B. „Stamm Sankt Georg“ oder „Sippe Falken“.' : 'z. B. „DV Köln, Stamm Sankt Georg“ oder „VCP Saarbrücken“.' }}</p>
        @error($key)<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
        @if ($mitBezirk)@error('bezirk')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror @endif
    </div>

    @if ($mitGast)
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="herkunft" value="gast" x-model="gast" @checked(old('herkunft') === 'gast') /> {{ $fields['herkunft']['options']['gast'] }}</label>
    @endif
</div>
