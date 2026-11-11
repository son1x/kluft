{{-- Rechte-Prüfung (docs/datenschutz-ueberblick.md). --}}
<x-filament-panels::page>
    @php($letzte = $this->letzte())
    @php($personen = $this->personen())
    <x-filament::section>
        <p class="text-sm">
            @if ($letzte)
                Zuletzt geprüft am <strong>{{ $letzte['am']->format('d.m.Y') }}</strong>{{ $letzte['von'] ? ' von '.$letzte['von'] : '' }}.
                {{ \App\Support\RechtePruefung::faellig() ? 'Die nächste Prüfung ist fällig.' : 'Nächste Prüfung bis '.$letzte['am']->copy()->addMonths(\App\Support\RechtePruefung::MONATE)->format('d.m.Y').'.' }}
            @else
                Noch nie geprüft. Seht die Liste durch und schließt die Prüfung oben rechts ab.
            @endif
        </p>
    </x-filament::section>

    <x-filament::section :heading="$personen->count().' '.($personen->count() === 1 ? 'Person' : 'Personen').' mit sensiblen Rechten'">
        @if ($personen->isEmpty())
            <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-shield-check" class="mx-auto mb-2 h-8 w-8" />
                Niemand außer euch hat sensible Rechte.
            </div>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($personen as $p)
                    <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            @php($url = $this->benutzerUrl($p['user']->id))
                            <p class="font-semibold">
                                @if ($url)<a href="{{ $url }}" class="hover:underline">{{ $p['user']->name }}</a>@else{{ $p['user']->name }}@endif
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ collect($p['rollen'])->map(fn ($r) => $r['name'].($r['gueltig_bis'] ? ' (bis '.$r['gueltig_bis']->format('d.m.Y').')' : ''))->implode(' · ') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Sensibel: {{ implode(', ', $p['rechte']) }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs">
                            <span class="text-gray-500 dark:text-gray-400">Zuletzt angemeldet: {{ $p['user']->last_login_at?->format('d.m.Y') ?? 'nie' }}</span>
                            @foreach ($p['hinweise'] as $hinweis)
                                <x-filament::badge color="warning">{{ $hinweis }}</x-filament::badge>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
