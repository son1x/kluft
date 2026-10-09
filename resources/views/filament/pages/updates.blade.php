<x-filament-panels::page>
    @php
        $modusText = [
        'off' => 'Aus – diese Installation wird anders aktualisiert (z. B. per GitHub-Deploy bei jedem Push).',
        'manual' => 'Von Hand – verfügbare Updates erscheinen hier und per Mail; installiert wird per Knopf.',
        'auto' => 'Automatisch – neue Versionen werden nachts nach dem Backup installiert (außer sie sind als „von Hand“ markiert).',
        ];
    @endphp

    <x-filament::section heading="Diese Installation">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500 dark:text-gray-400">Installierte Version</dt><dd class="font-semibold">Kluft {{ $version ?? 'unbekannt' }}@if ($revision) <span class="font-mono text-xs text-gray-500">({{ $revision }})</span>@endif</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Updates</dt><dd>{{ $modusText[$modus] }}</dd></div>
            @if ($modus !== 'off')
                <div><dt class="text-gray-500 dark:text-gray-400">Update-Adresse</dt><dd class="break-all">{{ $adresse ?: 'nicht eingerichtet (KLUFT_UPDATE_URL)' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Signaturschlüssel</dt><dd>{{ $schluessel ? 'eingerichtet – nur signierte Releases werden installiert' : 'fehlt (KLUFT_UPDATE_PUBLIC_KEY) – es wird nichts installiert' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Zuletzt geprüft</dt><dd>{{ $geprueft ? \Illuminate\Support\Carbon::createFromTimestamp($geprueft)->timezone(config('app.timezone'))->format('d.m.Y H:i') : 'noch nie' }}</dd></div>
            @endif
        </dl>
    </x-filament::section>

    @if ($anfrage)
        <x-filament::section heading="Update angefordert">
            <p class="text-sm">Kluft {{ $anfrage['version'] }} wurde am {{ \Illuminate\Support\Carbon::createFromTimestamp($anfrage['zeit'])->timezone(config('app.timezone'))->format('d.m.Y \u\m H:i') }} angefordert und startet mit dem nächsten Lauf des Cronjobs. Läuft der Cronjob nicht, passiert nichts – siehe Systemstatus.</p>
        </x-filament::section>
    @endif

    @if ($release && $modus !== 'off')
        <x-filament::section :heading="'Verfügbar: Kluft '.$release->version">
            @if ($release->veroeffentlicht)<p class="text-sm text-gray-500">Veröffentlicht {{ \Illuminate\Support\Carbon::parse($release->veroeffentlicht)->timezone(config('app.timezone'))->format('d.m.Y') }} · braucht PHP {{ $release->php }}@if (! $release->auto) · wird nicht automatisch installiert @endif</p>@endif
            @if ($release->aenderungen)
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($release->aenderungen as $zeile)<li>{{ $zeile }}</li>@endforeach
                </ul>
            @endif
        </x-filament::section>
    @elseif ($modus !== 'off')
        <x-filament::section heading="Keine neuere Version bekannt">
            <p class="text-sm">Diese Installation ist auf dem neuesten bekannten Stand. Geprüft wird täglich; mit „Nach Updates suchen“ sofort.</p>
        </x-filament::section>
    @endif

    @if ($lauf)
        <x-filament::section :heading="($lauf['ok'] ? 'Letztes Update: Kluft '.$lauf['version'].' installiert' : 'Letztes Update auf '.$lauf['version'].' fehlgeschlagen')">
            <p class="text-sm">{{ \Illuminate\Support\Carbon::createFromTimestamp($lauf['zeit'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }} · vorher {{ $lauf['vorher'] }}</p>
            <details class="mt-2 text-sm">
                <summary class="cursor-pointer font-medium">Protokoll</summary>
                <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-3 text-xs dark:bg-gray-900">{{ implode("\n", $lauf['protokoll']) }}</pre>
            </details>
        </x-filament::section>
    @endif
</x-filament-panels::page>
