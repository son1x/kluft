<x-filament-panels::page>
    <x-filament::section heading="Nacharbeitsliste" description="Importierte Beiträge sind Entwürfe. Vor der Veröffentlichung jeden Beitrag durchsehen (Personenbezug, Bildrechte, Links) und im Beitrag „Durchgesehen“ anhaken.">
        <p style="margin-bottom: 1rem; font-size: .875rem;">
            {{ $open->count() }} offen · {{ $reviewedCount }} durchgesehen · {{ $publishedCount }} veröffentlicht
        </p>
        @if ($open->isEmpty())
            <p style="font-size: .875rem;">Keine offenen Beiträge.</p>
        @else
            <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid rgba(127,127,127,.3);">
                        <th style="padding: .5rem .5rem .5rem 0;">Datum</th>
                        <th style="padding: .5rem;">Beitrag</th>
                        <th style="padding: .5rem;">Zu prüfen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($open as $post)
                        <tr style="border-bottom: 1px solid rgba(127,127,127,.15); vertical-align: top;">
                            <td style="padding: .5rem .5rem .5rem 0; white-space: nowrap;">{{ $post->published_at?->format('d.m.Y') }}</td>
                            <td style="padding: .5rem;">
                                <x-filament::link :href="$editUrl($post)">{{ $post->title }}</x-filament::link>
                            </td>
                            <td style="padding: .5rem;">
                                <ul style="list-style: disc; padding-left: 1rem;">
                                    @foreach ($post->import_issues ?? [] as $issue)
                                        <li>{{ $issue }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section heading="Importläufe" description="Ein Import ist wiederholbar: Neue Beiträge kommen dazu, im CMS bearbeitete oder veröffentlichte bleiben unverändert. Gestartet wird er von der Technik (GitHub-Aktion „WordPress-Import“ oder per SSH: php artisan wp:import, Testlauf mit --dry-run).">
        @forelse ($runs as $run)
            <details style="border-bottom: 1px solid rgba(127,127,127,.15); padding: .5rem 0;" @if ($loop->first) open @endif>
                <summary style="cursor: pointer; font-size: .875rem;">
                    <strong>#{{ $run->id }}</strong> · {{ $run->started_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') }}
                    · {{ $run->dry_run ? 'Testlauf' : 'Import' }} ab {{ $run->options['since'] ?? '?' }}
                    @if (! $run->finished_at) · <em>läuft oder abgebrochen</em> @endif
                    <br />
                    <span style="opacity: .8;">
                        @foreach ($run->counts ?? [] as $label => $n)
                            {{ $label }}: {{ $n }}@if (! $loop->last) · @endif
                        @endforeach
                    </span>
                </summary>
                <table style="width: 100%; font-size: .8125rem; margin-top: .5rem; border-collapse: collapse;">
                    @foreach ($run->log ?? [] as $entry)
                        <tr style="vertical-align: top;">
                            <td style="padding: .25rem .5rem .25rem 0; white-space: nowrap;">{{ $entry['date'] ?? '' }}</td>
                            <td style="padding: .25rem .5rem;">{{ $entry['title'] }}</td>
                            <td style="padding: .25rem .5rem; white-space: nowrap;">
                                <x-filament::badge :color="match ($entry['action']) { 'neu' => 'success', 'aktualisiert' => 'info', 'fehler' => 'danger', default => 'gray' }">{{ $entry['action'] }}</x-filament::badge>
                            </td>
                            <td style="padding: .25rem .5rem;">{{ $entry['message'] ?? '' }}@if (($entry['issues'] ?? 0) > 0) {{ $entry['issues'] }} Punkte zur Nacharbeit @endif</td>
                        </tr>
                    @endforeach
                </table>
            </details>
        @empty
            <p style="font-size: .875rem;">Noch kein Import gelaufen.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
