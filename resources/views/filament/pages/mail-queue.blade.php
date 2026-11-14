<x-filament-panels::page>
    @if ($nurLog)
        {{-- Mailer „log“: nichts verlässt den Server (docs/deploy.md „Mail“). --}}
        <x-filament::section heading="Mails werden nicht verschickt" icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <p style="font-size: .875rem; color: var(--danger-600);">
                Diese Website schreibt Mails nur ins Server-Protokoll (Mailer „log“). Anmeldebestätigungen, Anmeldecodes und Nachrichten aus dem Kontaktformular kommen nicht an, auch wenn im Nachrichten-Eingang „zugestellt“ steht.
            </p>
            <p style="margin-top: .5rem; font-size: .875rem;">
                Live-Seite: im GitHub-Environment die Variable <code>KLUFT_MAIL_LIVE=ja</code> setzen (dazu die Secrets <code>BREVO_SMTP_LOGIN</code> und <code>BREVO_SMTP_KEY</code>) und neu deployen. Testsystem: das Secret <code>MAIL_TEST_REDIRECT_TO</code> setzen.
            </p>
        </x-filament::section>
    @endif

    <x-filament::section heading="Warteschlange" description="Transaktionsmails (Anmeldungen, Kontaktformular, Dokumente) gehen über die Warteschlange raus: Der Cronjob verschickt sie jede Minute. Bei Fehlern versucht das System es bis zu fünfmal mit wachsendem Abstand.">
        <p style="font-size: .875rem;">
            <strong>{{ $pending }}</strong> {{ $pending === 1 ? 'Mail wartet' : 'Mails warten' }} auf den Versand
            @if ($oldestPending)
                (älteste seit {{ \Illuminate\Support\Carbon::createFromTimestamp($oldestPending)->timezone(config('app.timezone'))->format('d.m.Y H:i') }} Uhr)
            @endif
            · Tageslimit Brevo: {{ $dailyLimit }} Mails
        </p>
        @if ($pending > 0)
            <div style="margin-top: .75rem;">
                <x-filament::button size="sm" wire:click="jetztSenden" wire:loading.attr="disabled" wire:target="jetztSenden" icon="heroicon-o-paper-airplane">
                    <span wire:loading.remove wire:target="jetztSenden">Jetzt senden ({{ $pending }})</span>
                    <span wire:loading wire:target="jetztSenden">Sende …</span>
                </x-filament::button>
                <span style="font-size: .75rem; opacity: .7; margin-left: .5rem;">Arbeitet die Warteschlange sofort ab, ohne auf den Cronjob zu warten.</span>
            </div>
        @endif
        @if ($pending > 0 && $oldestPending && $oldestPending < now()->subMinutes(10)->timestamp)
            <p style="margin-top: .75rem; font-size: .875rem;" class="text-danger-600">Mails warten seit über zehn Minuten. Läuft der Cronjob? Oder ist das Tageslimit erreicht? @if (\App\Support\Features::enabled('roadmap'))(Siehe /roadmap → Cronjob.)@endif</p>
        @endif
    </x-filament::section>

    @if ($testUmleitung)
        <x-filament::section heading="Test-Empfänger*innen" description="Testumgebung: Alle Mails gehen an die Testadresse, außer an diese Adressen (zusammen höchstens zehn). Einträge aus dem GitHub-Secret MAIL_TEST_ALLOWLIST lassen sich nur dort ändern; hier lassen sich weitere ergänzen, ohne das Secret anzufassen.">
            <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
                <tbody>
                    @foreach ($testSecret as $eintrag)
                        <tr style="border-bottom: 1px solid rgba(127,127,127,.15);">
                            <td style="padding: .4rem 0;">{{ $istAdmin ? $eintrag : (str_starts_with($eintrag, '@') ? $eintrag : mb_substr($eintrag, 0, 1).'…@'.\Illuminate\Support\Str::after($eintrag, '@')) }}</td>
                            <td style="padding: .4rem; opacity: .7;">GitHub-Secret{{ in_array($eintrag, $testWirksam, true) ? '' : ' (über der Grenze, wirkt nicht)' }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                    @foreach ($testBackend as $eintrag)
                        <tr style="border-bottom: 1px solid rgba(127,127,127,.15);">
                            <td style="padding: .4rem 0;">{{ $eintrag }}</td>
                            <td style="padding: .4rem; opacity: .7;">hier ergänzt{{ in_array($eintrag, $testWirksam, true) ? '' : ' (über der Grenze, wirkt nicht)' }}</td>
                            <td style="text-align: right;">
                                @if ($istAdmin)
                                    <x-filament::link tag="button" color="danger" :wire:click="'testEmpfaengerEntfernen('.\Illuminate\Support\Js::from($eintrag).')'" wire:confirm="Diese Adresse wieder umleiten?">Entfernen</x-filament::link>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($testSecret === [] && $testBackend === [])
                        <tr><td style="padding: .4rem 0;">Keine – alle Mails gehen an die Testadresse.</td></tr>
                    @endif
                </tbody>
            </table>
            @if ($istAdmin)
                <form wire:submit="testEmpfaengerHinzufuegen" style="display: flex; gap: .5rem; align-items: flex-start; margin-top: 1rem;">
                    <div style="flex: 1;">
                        <x-filament::input.wrapper :valid="! $errors->has('neueTestAdresse')">
                            <x-filament::input type="text" wire:model="neueTestAdresse" placeholder="name@example.org oder @domain.de" />
                        </x-filament::input.wrapper>
                        @error('neueTestAdresse')<p class="text-danger-600" style="font-size: .8rem; margin-top: .25rem;">{{ $message }}</p>@enderror
                    </div>
                    <x-filament::button type="submit" size="sm">Freischalten</x-filament::button>
                </form>
            @endif
        </x-filament::section>
    @endif

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
