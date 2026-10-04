<x-filament-panels::page>
    @if ($testMode)
        <x-filament::section>
            <p class="text-sm"><strong>Testmodus:</strong> Nachrichten gehen nur an Nummern aus <code>WHATSAPP_TEST_ALLOWLIST</code>. Für die Testumgebung die kostenlose Testnummer von Meta verwenden, nicht die Büronummer.</p>
        </x-filament::section>
    @endif

    <x-filament::section heading="Zugangsdaten" description="Schritt 8 der Setup-Anleitung (docs/whatsapp_newsletter.md). Token und App-Secret werden verschlüsselt gespeichert und nicht wieder angezeigt.">
        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit">Speichern</x-filament::button>
        </form>
    </x-filament::section>

    @if ($configured)
        <x-filament::section heading="Webhook bei Meta eintragen" description="Schritt 9: App-Dashboard → WhatsApp → Konfiguration → Webhook bearbeiten.">
            <dl class="grid gap-3 text-sm sm:grid-cols-[12rem_1fr]">
                <dt class="font-medium text-gray-500 dark:text-gray-400">Callback-URL</dt>
                <dd><code class="select-all">{{ $webhookUrl }}</code></dd>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Verifizierungstoken</dt>
                <dd><code class="select-all">{{ $verifyToken }}</code></dd>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Felder abonnieren</dt>
                <dd><code>messages</code>, <code>message_template_status_update</code>, <code>phone_number_quality_update</code>, <code>account_update</code></dd>
            </dl>
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Danach oben „Webhook abonnieren“ klicken.</p>
        </x-filament::section>

        <x-filament::section heading="Status der Nummer">
            @if ($health['ok'] ?? false)
                @php($s = $health['status'] ?? [])
                <dl class="grid gap-3 text-sm sm:grid-cols-[12rem_1fr]">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Nummer</dt><dd>{{ $s['display_phone_number'] ?? '–' }} ({{ $s['verified_name'] ?? '–' }})</dd>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt><dd>{{ $s['status'] ?? '–' }} · Anzeigename {{ $s['name_status'] ?? '–' }}</dd>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Qualität</dt><dd>{{ $s['quality_rating'] ?? '–' }}{{ isset($quality['event']) ? ' · zuletzt gemeldet: '.$quality['event'] : '' }}</dd>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Versandlimit</dt><dd>{{ $s['messaging_limit_tier'] ?? '–' }}</dd>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Geprüft</dt><dd>{{ \Illuminate\Support\Carbon::parse($health['at'])->format('d.m.Y H:i') }}</dd>
                </dl>
            @else
                <p class="text-sm">{{ $health['error'] ?? 'Noch nicht geprüft – oben „Verbindung prüfen“.' }}</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
