{{-- Personen auf dem Gruppenfoto markieren: Person wählen, dann aufs Gesicht klicken. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{
            spots: $wire.$entangle('{{ $getStatePath() }}'),
            people: @js($people),
            selected: '',
            place(e) {
                if (! this.selected) { return; }
                const r = e.currentTarget.getBoundingClientRect();
                const x = Math.round((e.clientX - r.left) / r.width * 1000) / 10;
                const y = Math.round((e.clientY - r.top) / r.height * 1000) / 10;
                const id = Number(this.selected);
                this.spots = [...(this.spots || []).filter(s => Number(s.person_id) !== id), { person_id: id, x, y }];
                const next = this.people.find(p => ! this.spots.some(s => Number(s.person_id) === p.id));
                this.selected = next ? String(next.id) : '';
            },
            remove(id) { this.spots = (this.spots || []).filter(s => Number(s.person_id) !== Number(id)); },
            person(id) { return this.people.find(p => p.id === Number(id)) || { name: '?', initials: '?' }; },
            sorted() { return [...(this.spots || [])].sort((a, b) => a.x - b.x); },
        }">
        @if ($imageUrl)
            <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-bottom: .75rem;">
                <select x-model="selected" style="min-width: 16rem; padding: .4rem .6rem; border: 1px solid #c7bdad; border-radius: .375rem;" aria-label="Person zum Markieren">
                    <option value="">– Person wählen –</option>
                    <template x-for="p in people" :key="p.id">
                        <option :value="String(p.id)" x-text="p.name + (spots && spots.some(s => Number(s.person_id) === p.id) ? ' ✓' : '')"></option>
                    </template>
                </select>
                <span style="font-size: .85rem; opacity: .8;">Person wählen, dann im Foto auf ihr Gesicht klicken. Danach ist automatisch die nächste Person ohne Markierung gewählt.</span>
            </div>
            <div style="position: relative; user-select: none;" x-bind:style="{ cursor: selected ? 'crosshair' : 'default' }" x-on:click="place($event)">
                <img src="{{ $imageUrl }}" alt="" draggable="false" style="display: block; width: 100%; border-radius: .5rem;" />
                <template x-for="s in (spots || [])" :key="s.person_id">
                    <span x-bind:style="`position: absolute; left: ${s.x}%; top: ${s.y}%; transform: translate(-50%, -50%); width: 2.2rem; height: 2.2rem; border-radius: 9999px; border: 3px solid #fff; background: rgba(0, 48, 86, .85); color: #fff; font-size: .75rem; font-weight: 700; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 4px rgba(0,0,0,.4); pointer-events: none;`"
                        x-text="person(s.person_id).initials" x-bind:title="person(s.person_id).name"></span>
                </template>
            </div>
            <ol style="margin-top: .75rem; display: flex; flex-wrap: wrap; gap: .5rem; list-style: none; padding: 0;">
                <template x-for="s in sorted()" :key="s.person_id">
                    <li style="display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .5rem; border: 1px solid #c7bdad; border-radius: 9999px; font-size: .85rem;">
                        <span x-text="person(s.person_id).name"></span>
                        <button type="button" x-on:click="remove(s.person_id)" style="color: #810a1a; font-weight: 700;" x-bind:aria-label="'Markierung von ' + person(s.person_id).name + ' entfernen'">×</button>
                    </li>
                </template>
            </ol>
            <p style="margin-top: .5rem; font-size: .8rem; opacity: .75;">Die Reihenfolge „von links“ in der Bildunterschrift ergibt sich automatisch aus den Markierungen.</p>
        @else
            <p style="font-size: .9rem; opacity: .8;">Bitte zuerst oben ein Foto auswählen.</p>
        @endif
    </div>
</x-dynamic-component>
