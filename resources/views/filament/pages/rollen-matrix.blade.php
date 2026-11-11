{{-- Rollen und Rechte (App\Filament\Pages\RollenMatrix): Rechte je Gruppe (Zeilen) × Rollen (Spalten). --}}
<x-filament-panels::page>
    @php($vorlagen = $this->vorlagen())
    @php($eigene = $this->eigene())

    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-400">
        <span class="flex items-center gap-1.5"><x-filament::icon icon="heroicon-m-lock-closed" class="h-4 w-4" /> mitgelieferte Rolle (fest, als Vorlage)</span>
        <span class="flex items-center gap-1.5"><x-filament::icon icon="heroicon-m-shield-exclamation" class="h-4 w-4 text-danger-600" /> sensibel: Zugriff auf besonders schützenswerte Daten</span>
        <span class="flex items-center gap-1.5"><x-filament::icon icon="heroicon-m-globe-alt" class="h-4 w-4" /> gilt nur für die ganze Website</span>
    </div>

    @if ($eigene === [])
        <x-filament::section>
            <p class="text-sm">Noch keine eigenen Rollen. Mit „Neue Rolle“ kopierst du eine Vorlage, z. B. „Sektionsadmin“ als „Redaktion Fachbereich“ ohne Veranstaltungen, und hakst dann Rechte an oder ab.</p>
        </x-filament::section>
    @endif

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full min-w-max border-collapse text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th scope="col" class="sticky left-0 z-10 w-40 bg-white px-3 py-3 text-left font-semibold sm:w-72 sm:px-4 dark:bg-gray-900">Recht</th>
                    @foreach ($eigene as $rolle)
                        <th scope="col" class="bg-primary-50/60 px-2 py-3 text-center align-bottom font-semibold dark:bg-primary-500/10">
                            <span class="flex flex-col items-center gap-1">
                                <span class="w-24 break-words leading-tight hyphens-auto" lang="de">{{ $rolle->name }}</span>
                                <span class="text-xs font-normal text-gray-500">{{ $rolle->geltung === 'bereich' ? 'je Bereich' : 'ganze Website' }}</span>
                                <span class="flex items-center gap-0.5">
                                    {{ ($this->bearbeitenAction)(['rolle' => $rolle->id]) }}
                                    {{ ($this->loeschenAction)(['rolle' => $rolle->id]) }}
                                </span>
                            </span>
                        </th>
                    @endforeach
                    @foreach ($vorlagen as $schluessel => $v)
                        <th scope="col" class="px-2 py-3 text-center align-bottom font-medium text-gray-600 dark:text-gray-300">
                            <span class="flex flex-col items-center gap-1">
                                <x-filament::icon icon="heroicon-m-lock-closed" class="h-3.5 w-3.5 text-gray-400" />
                                <span class="w-20 break-words leading-tight hyphens-auto" lang="de">{{ $v['name'] }}</span>
                                <span class="text-xs font-normal text-gray-400">{{ $v['geltung'] === 'bereich' ? 'je Bereich' : 'ganze Website' }}</span>
                            </span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            @foreach ($this->gruppen() as $gruppe => $rechte)
                <tbody>
                    <tr>
                        <th scope="colgroup" colspan="{{ 1 + count($vorlagen) + count($eigene) }}" class="sticky left-0 bg-gray-50 px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">{{ $gruppe }}</th>
                    </tr>
                    @foreach ($rechte as $recht => $r)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                            <th scope="row" class="sticky left-0 z-10 w-40 bg-white px-3 py-2 text-left font-normal sm:w-72 sm:px-4 dark:bg-gray-900">
                                <span class="flex items-center gap-1.5 font-medium">
                                    {{ $r['label'] }}
                                    @if ($r['sensibel'] ?? false)<x-filament::icon icon="heroicon-m-shield-exclamation" class="h-4 w-4 text-danger-600" />@endif
                                    @unless ($r['bereich'])<x-filament::icon icon="heroicon-m-globe-alt" class="h-4 w-4 text-gray-400" />@endunless
                                </span>
                                <span class="hidden text-xs text-gray-500 sm:block dark:text-gray-400">{{ $r['text'] }}</span>
                            </th>
                            @foreach ($eigene as $rolle)
                                @php($an = in_array($recht, (array) $rolle->rechte, true))
                                @php($passt = \App\Support\Rechte::passtZuGeltung($recht, $rolle->geltung))
                                <td class="bg-primary-50/60 px-2 py-1 text-center dark:bg-primary-500/10">
                                    @if ($passt)
                                        <button type="button" wire:click="umschalten({{ $rolle->id }}, @js($recht))" wire:loading.attr="disabled"
                                                aria-pressed="{{ $an ? 'true' : 'false' }}" aria-label="{{ $r['label'] }} für {{ $rolle->name }}"
                                                @class([
                                                    'mx-auto flex h-11 w-11 items-center justify-center rounded-lg border transition focus-visible:outline-2 focus-visible:outline-primary-600',
                                                    'border-primary-600 bg-primary-600 text-white' => $an,
                                                    'border-gray-300 bg-white text-transparent hover:border-primary-400 dark:border-white/20 dark:bg-gray-900' => ! $an,
                                                ])>
                                            <x-filament::icon icon="heroicon-m-check" class="h-5 w-5" />
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400" title="Gilt nur für die ganze Website">–</span>
                                    @endif
                                </td>
                            @endforeach
                            @foreach ($vorlagen as $v)
                                <td class="px-2 py-2 text-center">
                                    @if (in_array($recht, $v['rechte'], true))
                                        <x-filament::icon icon="heroicon-m-check" class="mx-auto h-5 w-5 text-gray-500" aria-label="ja" />
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600" aria-label="nein">–</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
        </table>
    </div>

    <x-filament::section heading="So funktioniert es" collapsible collapsed>
        <ul class="list-disc space-y-1 ps-5 text-sm">
            <li><strong>Geltung:</strong> Rollen „je Bereich“ wählst du bei der Vergabe mit Stufe oder Fachbereich aus (z. B. „Redaktion Fachbereich – Spiritualität“). Rollen „ganze Website“ gelten überall.</li>
            <li><strong>Vorlagen</strong> sind die mitgelieferten Rollen; sie bleiben fest, damit Updates sie sicher weiterentwickeln können.</li>
            <li><strong>Sensible Rechte</strong> (Schild) nur bewusst vergeben. Bei der Vergabe einer Rolle mit sensiblen Rechten werden die Admins informiert.</li>
            <li>Technik (Funktionen, Backups, Updates, Wartungsmodus) bleibt der Rolle Admin vorbehalten.</li>
            <li>Jede Änderung steht im Protokoll.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
