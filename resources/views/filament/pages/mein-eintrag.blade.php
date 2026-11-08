{{-- Mein Konto → Mein Eintrag auf der Website (MeinEintrag). --}}
<x-filament-panels::page>
    @php($eintraege = $this->eintraege())
    @php($person = $this->aktuellePerson())

    @if ($eintraege->count() > 1)
        <x-filament::section heading="Deine Einträge">
            <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
                @foreach ($eintraege as $eintrag)
                    <x-filament::button size="sm" :color="$eintrag->id === $personId ? 'primary' : 'gray'" wire:click="waehlen({{ $eintrag->id }})">
                        {{ $eintrag->gremiumLabel() }}{{ $eintrag->role ? ' – '.$eintrag->role : '' }}
                    </x-filament::button>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    @if ($person)
        <x-filament::section heading="So stehst du auf der Website" description="Name, Funktion, Gremium und E-Mail pflegt die Verwaltung. Sprich sie an, wenn hier etwas nicht stimmt.">
            <div style="display: flex; gap: 1rem; align-items: center; font-size: .875rem;">
                @if ($person->photo)
                    <img src="{{ $person->photo->url() }}" alt="" style="width: 72px; height: 72px; border-radius: 9999px; object-fit: cover;">
                @endif
                <dl style="display: grid; grid-template-columns: max-content 1fr; gap: .25rem 1rem;">
                    <dt style="font-weight: 600;">Name</dt><dd>{{ $person->name }}</dd>
                    @if ($person->role)<dt style="font-weight: 600;">Funktion</dt><dd>{{ $person->role }}</dd>@endif
                    <dt style="font-weight: 600;">Gremium</dt><dd>{{ $person->gremiumLabel() }}</dd>
                    @if ($person->publicEmail())<dt style="font-weight: 600;">E-Mail</dt><dd>{{ $person->publicEmail() }}</dd>@endif
                    <dt style="font-weight: 600;">Sichtbar</dt>
                    <dd>
                        @if ($person->consent && $person->active)
                            ja
                            @if ($url = $person->oeffentlicheUrl())
                                · <a href="{{ $url }}" target="_blank" rel="noopener" style="text-decoration: underline;">ansehen</a>
                            @endif
                        @elseif (! $person->active)
                            nein (von der Verwaltung ausgeblendet)
                        @else
                            nein (du hast die Veröffentlichung nicht erlaubt)
                        @endif
                    </dd>
                </dl>
            </div>
        </x-filament::section>

        <x-filament::section heading="Selbst ändern">
            <form wire:submit="speichern" style="display: grid; gap: 1rem; justify-items: start;">
                {{ $this->form }}
                <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
                    <x-filament::button type="submit">Speichern</x-filament::button>
                    @if ($person->photo)
                        <x-filament::button color="gray" wire:click="fotoEntfernen" wire:confirm="Foto vom Eintrag nehmen?">Foto entfernen</x-filament::button>
                    @endif
                </div>
            </form>
        </x-filament::section>
    @endif
</x-filament-panels::page>
