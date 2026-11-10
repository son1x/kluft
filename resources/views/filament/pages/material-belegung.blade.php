{{-- Belegungskalender je Material (docs/materialverleih.md). --}}
<x-filament-panels::page>
    @php($material = $this->material())
    @php($wochen = $this->wochen())
    @php($farben = ['frei' => 'bg-white dark:bg-gray-900', 'teilweise' => 'bg-amber-100 dark:bg-amber-900/40', 'voll' => 'bg-red-100 dark:bg-red-900/40'])

    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-filament::icon-button icon="heroicon-o-chevron-left" wire:click="blaettern(-1)" label="Vorheriger Monat" size="lg" />
                <h2 class="min-w-40 text-center text-lg font-semibold">{{ $this->start()->locale('de')->isoFormat('MMMM YYYY') }}</h2>
                <x-filament::icon-button icon="heroicon-o-chevron-right" wire:click="blaettern(1)" label="Nächster Monat" size="lg" />
                <x-filament::button color="gray" size="sm" wire:click="heute">Heute</x-filament::button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $material->menge }} {{ $material->menge === 1 ? 'Stück' : 'Stück' }}@if ($material->ort) · {{ $material->ort }}@endif
            </p>
        </div>

        <div class="mt-4 grid grid-cols-7 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 text-sm dark:border-white/10 dark:bg-white/10" role="grid" aria-label="Belegung im Monat">
            @foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $wt)
                <div class="bg-gray-50 px-2 py-1 text-center text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300" role="columnheader">{{ $wt }}</div>
            @endforeach
            @foreach ($wochen as $woche)
                @foreach ($woche as $t)
                    <div role="gridcell"
                         class="min-h-16 p-1.5 {{ $farben[$t['stufe']] }} {{ $t['imMonat'] ? '' : 'opacity-40' }}"
                         aria-label="{{ $t['tag']->format('d.m.Y') }}: {{ $t['fest'] }} von {{ $material->menge }} vergeben{{ $t['angefragt'] ? ', '.$t['angefragt'].' angefragt' : '' }}">
                        <div class="flex items-start justify-between">
                            <span class="{{ $t['tag']->isToday() ? 'rounded-full bg-primary-600 px-1.5 font-bold text-white' : 'font-medium' }}">{{ $t['tag']->day }}</span>
                            @if ($t['angefragt'] > 0)
                                <span class="h-2 w-2 rounded-full bg-sky-500" title="{{ $t['angefragt'] }} angefragt"></span>
                            @endif
                        </div>
                        @if ($t['fest'] > 0)
                            <div class="mt-1 text-xs font-semibold {{ $t['stufe'] === 'voll' ? 'text-red-700 dark:text-red-300' : 'text-amber-800 dark:text-amber-300' }}">
                                {{ $material->menge > 1 ? $t['fest'].'/'.$material->menge : 'vergeben' }}
                            </div>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>

        <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm border border-gray-300 bg-white"></span> frei</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-amber-100"></span> teilweise vergeben</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-red-100"></span> alles vergeben</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-sky-500"></span> offene Anfrage</span>
        </div>
    </x-filament::section>

    <x-filament::section heading="Reservierungen in diesem Monat">
        @php($liste = $this->reservierungen())
        @if ($liste->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">Keine Reservierungen. Das Material ist den ganzen Monat frei.</p>
        @else
            <ul class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                @foreach ($liste as $r)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ \App\Filament\Resources\Material\ReservierungResource::getUrl('edit', ['record' => $r]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                            {{ $r->zeitraum() }} · {{ $r->name }}{{ $r->gruppierung ? ' ('.$r->gruppierung.')' : '' }}
                        </a>
                        <span class="flex items-center gap-2">
                            @if ($material->menge > 1)<span class="text-gray-600 dark:text-gray-400">{{ $r->menge }} Stück</span>@endif
                            <x-filament::badge :color="\App\Models\MaterialReservierung::FARBEN[$r->status] ?? 'gray'">{{ \App\Models\MaterialReservierung::STATUS[$r->status] }}</x-filament::badge>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
