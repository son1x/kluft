<x-filament-panels::page>
    <x-filament::section heading="Warteschlange" description="Transaktionsmails (Anmeldungen, Kontaktformular, Dokumente) gehen über die Warteschlange raus: Der Cronjob verschickt sie jede Minute. Bei Fehlern versucht das System es bis zu fünfmal mit wachsendem Abstand.">
        <p style="font-size: .875rem;">
            <strong>{{ $pending }}</strong> {{ $pending === 1 ? 'Mail wartet' : 'Mails warten' }} auf den Versand
            @if ($oldestPending)
                (älteste seit {{ \Illuminate\Support\Carbon::createFromTimestamp($oldestPending)->timezone(config('app.timezone'))->format('d.m.Y H:i') }} Uhr)
            @endif
            · Tageslimit Brevo: {{ $dailyLimit }} Mails
        </p>
        @if ($pending > 0 && $oldestPending && $oldestPending < now()->subMinutes(10)->timestamp)
            <p style="margin-top: .75rem; font-size: .875rem;" class="text-danger-600">Mails warten seit über zehn Minuten. Läuft der Cronjob? Oder ist das Tageslimit erreicht? (Siehe /roadmap → Cronjob.)</p>
        @endif
    </x-filament::section>

    <x-filament::section heading="Fehlgeschlagen" description="Diese Mails konnten auch nach mehreren Versuchen nicht verschickt werden. Einträge verschwinden nach 14 Tagen automatisch.">
        @if ($failed->isEmpty())
            <p style="font-size: .875rem;">Keine fehlgeschlagenen Mails.</p>
        @else
            <div style="margin-bottom: 1rem;">
                <x-filament::button size="sm" wire:click="retryAll" wire:confirm="Alle fehlgeschlagenen Mails erneut versuchen?">Alle erneut versuchen</x-filament::button>
            </div>
            <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid rgba(127,127,127,.3);">
                        <th style="padding: .5rem .5rem .5rem 0;">Zeitpunkt</th>
                        <th style="padding: .5rem;">Mail</th>
                        <th style="padding: .5rem;">Empfänger*in</th>
                        <th style="padding: .5rem;">Fehler</th>
                        <th style="padding: .5rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($failed as $job)
                        <tr style="border-bottom: 1px solid rgba(127,127,127,.15); vertical-align: top;">
                            <td style="padding: .5rem .5rem .5rem 0; white-space: nowrap;">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</td>
                            <td style="padding: .5rem;">{{ $job->label }}</td>
                            <td style="padding: .5rem;">{{ $job->recipient ?? '–' }}</td>
                            <td style="padding: .5rem; word-break: break-word;">{{ $job->error }}</td>
                            <td style="padding: .5rem; white-space: nowrap;">
                                <x-filament::link tag="button" wire:click="retry('{{ $job->uuid }}')">Erneut versuchen</x-filament::link>
                                ·
                                <x-filament::link tag="button" color="danger" wire:click="forget('{{ $job->uuid }}')" wire:confirm="Diese Mail endgültig löschen?">Löschen</x-filament::link>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
