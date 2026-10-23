<x-filament-panels::page>
    @php($tab = fn (string $key, string $label) => '<button type="button" wire:click="zeige(\''.$key.'\')" style="padding: .4rem .9rem; border-radius: .5rem; font-size: .875rem; font-weight: 600; '.($ansicht === $key ? 'background: #003056; color: #fff;' : 'background: rgba(127,127,127,.12);').'">'.e($label).'</button>')
    <div style="display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;">
        {!! $tab('zusammenfassung', 'Zusammenfassung') !!}
        {!! $tab('einzeln', 'Einzelne Antworten') !!}
        @if ($umfrage->gewinnspiel)
            {!! $tab('gewinnspiel', 'Gewinnspiel') !!}
        @endif
        <span style="margin-left: auto; font-size: .875rem; opacity: .8;">
            {{ $anzahl }} {{ $anzahl === 1 ? 'Teilnahme' : 'Teilnahmen' }}
            @if ($einladungen) · {{ $einladungen['teilgenommen'] }} von {{ $einladungen['gesamt'] }} Eingeladenen @endif
            · {{ \App\Models\Umfrage::STATUS[$umfrage->status] ?? $umfrage->status }}{{ $umfrage->istOffen() ? ' (nimmt Antworten an)' : '' }}
            @unless ($umfrage->nurEingeladene())
                · <a href="{{ $umfrage->url() }}" target="_blank" rel="noopener" style="text-decoration: underline;">Link</a>
            @endunless
        </span>
    </div>

    @if ($ansicht === 'zusammenfassung')
        <x-filament::section>
            @if ($anzahl === 0)
                <p style="font-size: .875rem;">Noch keine Antworten.</p>
            @else
                @foreach ($auswertung['fragen'] as $e)
                    @include('umfragen.partials.frage-ergebnis', ['e' => $e])
                @endforeach
            @endif
        </x-filament::section>
    @elseif ($ansicht === 'einzeln')
        <x-filament::section>
            @if (! $antwort)
                <p style="font-size: .875rem;">Noch keine Antworten.</p>
            @else
                <div style="display: flex; gap: .5rem; align-items: center; margin-bottom: 1rem;">
                    <x-filament::button size="sm" color="gray" wire:click="blaettern(-1)" :disabled="$nr <= 1" icon="heroicon-o-chevron-left">Zurück</x-filament::button>
                    <span style="font-size: .875rem;">Teilnahme {{ $nr }} von {{ $anzahl }} · {{ $antwort->eingegangen_am->format('d.m.Y') }}
                        @if (! $umfrage->anonym && ($antwort->name || $antwort->email)) · <strong>{{ $antwort->name }}</strong> {{ $antwort->email }}@endif</span>
                    <x-filament::button size="sm" color="gray" wire:click="blaettern(1)" :disabled="$nr >= $anzahl" icon="heroicon-o-chevron-right" icon-position="after">Weiter</x-filament::button>
                </div>
                <dl style="font-size: .875rem;">
                    @foreach ($fragen as $f)
                        @if ($f->typ === 'abschnitt')
                            <dt style="margin-top: 1rem; font-weight: bold; font-size: 1rem;">{{ $f->text }}</dt>
                        @else
                            @php($e = \App\Filament\Resources\Umfragen\Pages\AuswertungUmfrage::wert($antwort, $f->schluessel))
                            <dt style="margin-top: .75rem; font-weight: 600;">{{ $f->text }}</dt>
                            <dd style="margin: .15rem 0 0;">
                                @if ($e === null)
                                    <span style="opacity: .6;">nicht gezeigt</span>
                                @else
                                    {!! nl2br(e(\App\Services\Umfragen\Auswertung::wertText($e['wert'] ?? null))) !!}
                                    @if (filled($e['sonstiges'] ?? null))<br /><em>Sonstiges:</em> {{ $e['sonstiges'] }}@endif
                                    @if (filled($e['kommentar'] ?? null))<br /><em>Kommentar:</em> {!! nl2br(e($e['kommentar'])) !!}@endif
                                @endif
                            </dd>
                        @endif
                    @endforeach
                </dl>
            @endif
        </x-filament::section>
    @else
        <x-filament::section heading="Gewinnspiel" description="Name und Mail sind getrennt von den Antworten gespeichert. Nach der Auslosung bitte löschen.">
            @if ($gewinnspiel->isEmpty())
                <p style="font-size: .875rem;">Keine Einträge.</p>
            @else
                <div style="display: flex; gap: .5rem; margin-bottom: 1rem;">
                    <x-filament::button size="sm" wire:click="auslosen" icon="heroicon-o-gift">Gewinner*in auslosen</x-filament::button>
                    <x-filament::button size="sm" color="danger" wire:click="gewinnspielLoeschen" wire:confirm="Alle Gewinnspiel-Einträge dieser Umfrage löschen?">Alle Einträge löschen</x-filament::button>
                </div>
                <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
                    <thead><tr style="text-align: left; border-bottom: 1px solid rgba(127,127,127,.3);"><th style="padding: .4rem 0;">Name</th><th>E-Mail</th><th>Datum</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($gewinnspiel as $g)
                            <tr style="border-bottom: 1px solid rgba(127,127,127,.15);">
                                <td style="padding: .4rem 0;">{{ $g->name }}</td><td>{{ $g->email }}</td><td>{{ $g->eingegangen_am->format('d.m.Y') }}</td>
                                <td>@if ($g->gewonnen)<strong>Gewonnen</strong>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
