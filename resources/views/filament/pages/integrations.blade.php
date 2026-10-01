<x-filament-panels::page>
    <x-filament::section heading="Instagram" description="Blogbeiträge aus dem Editor auf Instagram teilen (Pflichtenheft 6.4).">
        @if ($instagram)
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Konto</dt>
                    <dd>{{ '@'.($instagram->account_name ?: $instagram->account_id) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Token gültig bis (ca.)</dt>
                    <dd @class(['text-danger-600 font-medium' => $instagram->expires_at?->lt(now()->addDays(7))])>{{ $instagram->expires_at?->format('d.m.Y') ?? 'unbekannt' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Zuletzt erneuert</dt>
                    <dd>{{ $instagram->refreshed_at?->format('d.m.Y H:i') ?? '–' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Letzter Fehler</dt>
                    <dd @class(['text-danger-600' => $instagram->last_error])>{{ $instagram->last_error ?? 'keiner' }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Das Token wird wöchentlich automatisch erneuert. Schlägt das fehl, bekommen alle Admins eine E-Mail.</p>
        @else
            <p class="text-sm">Noch nicht verbunden. Voraussetzungen: Instagram-Business- oder Creator-Konto, eine Meta-App mit „Instagram API mit Instagram-Login“ und der Berechtigung <code>instagram_business_content_publish</code>. Dort ein Long-Lived-Token erzeugen und unten eintragen.</p>
        @endif

        <form wire:submit="saveInstagram" style="margin-top: 1.5rem; display: grid; gap: 1rem; justify-items: start;">
            {{ $this->form }}
            <x-filament::button type="submit">{{ $instagram ? 'Neues Token speichern' : 'Verbinden' }}</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
