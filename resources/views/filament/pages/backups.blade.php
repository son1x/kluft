{{-- Verwaltung → Backups (App\Filament\Pages\Backups). --}}
@php($d = $this->daten())
<x-filament-panels::page>
    @unless ($d['passwort'])
        <x-filament::section>
            <x-slot name="heading">Kein Backup-Passwort</x-slot>
            <p class="text-sm">BACKUP_PASSWORD fehlt im GitHub-Environment (mindestens 16 Zeichen). Ohne Passwort gibt es keine Backups und kein Einspielen.</p>
        </x-filament::section>
    @endunless

    <x-filament::section>
        <x-slot name="heading">Stand</x-slot>
        <dl class="grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold">Letztes Backup</dt><dd>{{ $d['letztes'] ? \Illuminate\Support\Carbon::parse($d['letztes']['at'])->timezone(config('app.timezone'))->format('d.m.Y H:i').' · '.$d['letztes']['file'].($d['letztes']['offsite'] ? ' · auch extern (WebDAV)' : '') : 'noch keins' }}</dd></div>
            <div><dt class="font-semibold">Letzte Wiederherstellungsprobe</dt><dd>{{ $d['pruefung'] ? \Illuminate\Support\Carbon::parse($d['pruefung']['at'] ?? 'now')->timezone(config('app.timezone'))->format('d.m.Y H:i').' · '.(($d['pruefung']['ok'] ?? false) ? 'in Ordnung' : 'FEHLER') : 'noch keine' }}</dd></div>
        </dl>
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Automatisch jede Nacht um 2 Uhr, 14 Tage aufbewahrt; sonntags wird das neueste probeweise entschlüsselt.</p>
        @if ($d['stand'])
            <p class="mt-3 text-sm">
                Letzter Auftrag ({{ match ($d['stand']['art']) { 'backup' => 'Backup erstellen', 'uebertragen' => 'auf andere Website übertragen', default => 'Backup einspielen' } }}):
                {{ match ($d['stand']['status']) { 'wartet' => 'wartet', 'laeuft' => 'läuft', 'fertig' => 'fertig', default => 'fehlgeschlagen' } }} –
                <a class="font-semibold underline" href="{{ route('backup.stand', $d['stand']['token']) }}">Verlauf ansehen</a>
            </p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Vorhandene Backups</x-slot>
        @if ($d['liste'] === [])
            <p class="text-sm">Noch keine.</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($d['liste'] as $beschriftung)
                    <li>{{ $beschriftung }}</li>
                @endforeach
            </ul>
        @endif
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Auch per SSH: <code>php artisan backup:run</code>, <code>php artisan backup:einspielen &lt;datei&gt;</code>. Ohne Backend: Actions → „Server-Prüfung“ → „backup:run“.</p>
    </x-filament::section>
</x-filament-panels::page>
