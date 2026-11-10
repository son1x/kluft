{{-- Dashboard: Was steht an (App\Support\WasStehtAn). Liste wie in iOS: ganze Zeile tippbar, Symbol, Titel, Untertitel, Zahl, Pfeil. --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Was steht an" icon="heroicon-o-bell-alert">
        @if ($eintraege === [])
            <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 text-success-600 dark:text-success-400" />
                <p>Alles erledigt. Hier erscheint, was deine Aufmerksamkeit braucht: Freigaben, Nachrichten, Anmeldungen, Fristen.</p>
            </div>
        @else
            @php($farben = [
                'danger' => 'bg-danger-50 text-danger-600 dark:bg-danger-500/10 dark:text-danger-400',
                'warning' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400',
                'info' => 'bg-info-50 text-info-600 dark:bg-info-500/10 dark:text-info-400',
                'gray' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400',
            ])
            <ul class="-mx-2 divide-y divide-gray-100 dark:divide-white/5" role="list">
                @foreach ($eintraege as $e)
                    <li>
                        <a href="{{ $e['url'] }}" class="flex min-h-14 items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-primary-600 dark:hover:bg-white/5">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $farben[$e['farbe']] ?? $farben['gray'] }}">
                                <x-filament::icon :icon="$e['icon']" class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block line-clamp-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $e['titel'] }}</span>
                                <span class="block line-clamp-2 text-sm text-gray-600 dark:text-gray-400">{{ $e['text'] }}</span>
                            </span>
                            @isset($e['anzahl'])
                                <x-filament::badge :color="$e['farbe']">{{ $e['anzahl'] }}</x-filament::badge>
                            @endisset
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 shrink-0 text-gray-400" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
