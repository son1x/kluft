<x-filament-panels::page>
    @php($euro = fn (?int $c) => $c === null ? '–' : number_format($c / 100, 2, ',', '.').' €')
    @forelse ($zeilen as $zeile)
        @php($e = $zeile['event'])
        <x-filament::section :heading="$e->title" :description="$e->starts_at->format('d.m.Y').' · '.(\App\Support\Zuschuesse::ARTEN[$e->zuschuss_art] ?? 'Art fehlt')">
            <x-slot name="headerEnd">
                <x-filament::link :href="\App\Filament\Resources\Events\EventResource::getUrl('zuschuss', ['record' => $e])">Zuschussantrag öffnen</x-filament::link>
            </x-slot>
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b"><th class="py-1">Zuschussgeber</th><th class="py-1">Frist</th><th class="py-1">Stand</th><th class="py-1 text-right">Schätzung</th><th class="py-1 text-right">Beantragt</th><th class="py-1 text-right">Bewilligt</th><th class="py-1">Ausgezahlt</th></tr></thead>
                <tbody>
                    @foreach ($zeile['geber'] as $z)
                        @php($knapp = $z['status'] === 'offen' && $z['frist']->lte(now()->addDays(14)))
                        <tr class="border-b border-gray-100">
                            <td class="py-1">{{ $z['name'] }}</td>
                            <td class="py-1" @if ($knapp) style="color: rgb(220 38 38); font-weight: 600" @endif>{{ $z['frist']->format('d.m.Y') }}</td>
                            <td class="py-1">{{ \App\Support\Zuschuesse\Antrag::STATUS[$z['status']] }}</td>
                            <td class="py-1 text-right">{{ $euro($z['geschaetzt']) }}</td>
                            <td class="py-1 text-right">{{ $euro($z['beantragt']) }}</td>
                            <td class="py-1 text-right">{{ $euro($z['bewilligt']) }}</td>
                            <td class="py-1">{{ $z['ausgezahlt_am'] ? \Illuminate\Support\Carbon::parse($z['ausgezahlt_am'])->format('d.m.Y') : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @empty
        <x-filament::section><p class="text-sm">Noch keine Veranstaltung mit „Zuschuss beantragen“ in den letzten 18 Monaten.</p></x-filament::section>
    @endforelse
</x-filament-panels::page>
