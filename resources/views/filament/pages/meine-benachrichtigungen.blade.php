{{-- Mein Konto → Meine Benachrichtigungen: Mail-Hinweise wählen, Stand der Push-Nachrichten. --}}
<x-filament-panels::page>
    <x-filament::section heading="Per Mail" icon="heroicon-o-envelope">
        @if ($this->mailWaehlbar())
            <form wire:submit="speichern" style="display: grid; gap: 1rem; justify-items: start;">
                {{ $this->form }}
                <x-filament::button type="submit">Speichern</x-filament::button>
            </form>
        @else
            <p style="font-size: .875rem;">Für deine Rollen gibt es derzeit keine Hinweise per Mail zum Auswählen. Mails zu deinem Konto (z. B. Anmeldecodes, neue Rechte) bekommst du immer.</p>
        @endif
    </x-filament::section>

    @if ($this->pushAn())
        <x-filament::section heading="Aufs Handy (Push)" icon="heroicon-o-bell-alert">
            <div style="display: grid; gap: .75rem; font-size: .875rem;">
                @if ($this->pushEingerichtet())
                    @php($hinweise = $this->pushHinweise())
                    <p>Push-Nachrichten sind eingeschaltet.
                        @if ($hinweise === [])
                            Gemeldet wird derzeit nichts.
                        @else
                            Gemeldet wird: {{ implode(', ', $hinweise) }}.
                        @endif
                    </p>
                @else
                    <p>Push-Nachrichten sind noch nicht eingeschaltet. Dafür brauchst du die kostenlose App „ntfy“.</p>
                @endif
                <div>
                    <x-filament::button tag="a" :href="\App\Filament\Pages\PushSettings::getUrl()" color="gray" icon="heroicon-o-bell-alert">
                        {{ $this->pushEingerichtet() ? 'Push-Nachrichten ändern' : 'Push-Nachrichten einrichten' }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
