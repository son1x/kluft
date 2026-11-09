{{-- Verwaltung → Wartungsmodus (App\Filament\Pages\Wartungsmodus). --}}
@php($stand = $this->stand())
<x-filament-panels::page>
    @if ($stand['aktiv'])
        <x-filament::section>
            <x-slot name="heading">Der Wartungsmodus ist an</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Seit {{ $stand['seit']?->timezone(config('app.timezone'))->format('d.m.Y, H:i') }} Uhr sehen Besucher*innen die Wartungsseite.
                @if ($stand['nur_admins']) Ins Backend kommen nur Admins. @endif
                Angemeldet siehst du die Website wie gewohnt, mit rotem Band.
            </p>
        </x-filament::section>
    @else
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Schließt die Website vorübergehend für Besucher*innen, z. B. beim Umzug auf einen neuen Server. Wer angemeldet ist, sieht sie weiter.
            Anmeldung, Backend und Systemstatus bleiben erreichbar, Mails und geplante Aufgaben laufen weiter. Jede Änderung steht im Protokoll.
        </p>
    @endif

    <form wire:submit="einschalten" class="space-y-6">
        {{ $this->form }}
        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" :color="$stand['aktiv'] ? 'gray' : 'danger'" icon="heroicon-o-wrench-screwdriver">
                {{ $stand['aktiv'] ? 'Angaben ändern' : 'Wartungsmodus einschalten' }}
            </x-filament::button>
            @if ($stand['aktiv'])
                <x-filament::button type="button" wire:click="ausschalten" color="success" icon="heroicon-o-check-circle">Wartungsmodus ausschalten</x-filament::button>
            @endif
        </div>
    </form>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Kommst du nicht ins Backend: GitHub → Actions → „Server-Prüfung“ → „wartung:aus“, oder auf dem Server <code>php artisan kluft:wartung aus</code>.
        Der Schalter liegt in der Datenbank und wandert beim Kopieren mit.
    </p>
</x-filament-panels::page>
