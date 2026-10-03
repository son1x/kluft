<x-filament-panels::page>
    @php($topic = $this->topic())

    @if (! $topic)
        <x-filament::section heading="Push-Nachrichten einschalten" description="Benachrichtigungen aufs Handy, z. B. bei neuen Anmeldungen, Beiträgen zur Freigabe oder neuen Rechten – zusätzlich zu den E-Mails.">
            <div class="space-y-3 text-sm">
                <p>Dafür nutzen wir <strong>ntfy</strong>, eine kostenlose App ohne Konto und ohne Werbung:</p>
                <ol class="list-decimal space-y-1 pl-5">
                    <li>App „ntfy“ installieren (Android: Google Play oder F-Droid; iPhone: App Store).</li>
                    <li>Hier auf „Einschalten“ klicken – du bekommst ein persönliches, geheimes Thema.</li>
                    <li>Das Thema in der App abonnieren und eine Testnachricht schicken.</li>
                </ol>
                <p class="text-gray-500 dark:text-gray-400">Die Nachrichten enthalten keine Namen oder Kontaktdaten, nur z. B. „Neue Anmeldung: Wölflingstag – 12/30 Plätze belegt“. Details siehst du wie gewohnt im Backend.</p>
            </div>
            <div class="mt-4">
                <x-filament::button wire:click="enable" icon="heroicon-o-bell-alert">Einschalten</x-filament::button>
            </div>
        </x-filament::section>
    @else
        <x-filament::section heading="1. In der ntfy-App abonnieren">
            <div class="space-y-3 text-sm">
                <p>In der App auf <strong>+</strong> („Thema abonnieren“) tippen und dieses Thema eintragen{{ $this->server() !== 'https://ntfy.sh' ? ', als Server '.$this->server().' wählen' : '' }}:</p>
                <p><code class="select-all rounded bg-gray-100 px-2 py-1 text-base dark:bg-white/10">{{ $topic }}</code></p>
                <p>Oder auf dem Handy diesen Link öffnen: <a href="{{ $this->server().'/'.$topic }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">{{ $this->server().'/'.$topic }}</a></p>
                <p class="text-gray-500 dark:text-gray-400">Das Thema ist wie ein Passwort: Wer es kennt, kann mitlesen. Nicht weitergeben. Falls doch passiert: „Neues Thema erzeugen“.</p>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button wire:click="sendTest" color="gray" icon="heroicon-o-paper-airplane">Testnachricht senden</x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="2. Was soll gemeldet werden?">
            <form wire:submit="save" class="space-y-4">
                {{ $this->form }}
                <x-filament::button type="submit">Speichern</x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section heading="Thema und Abschalten" collapsible collapsed>
            <div class="flex flex-wrap gap-2">
                <x-filament::button wire:click="regenerate" wire:confirm="Neues Thema erzeugen? Das alte Abo in der App bekommt danach nichts mehr." color="warning" icon="heroicon-o-arrow-path">Neues Thema erzeugen</x-filament::button>
                <x-filament::button wire:click="disable" wire:confirm="Push-Nachrichten ausschalten?" color="danger" icon="heroicon-o-bell-slash">Ausschalten</x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
