@extends('layouts.app')

{{-- Statusseite einer Bestellung (Link mit Token aus der Mail). Erwartet: $bestellung, $token --}}

@section('seo_title', 'Deine Bestellung – '.config('dpsg.site.name'))

@php($stand = $bestellung->stand())

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-12">
        <p class="text-sm font-bold uppercase tracking-wider text-text-secondary">Sammelbestellung {{ $bestellung->runde->name }}</p>
        <h1 class="mt-1 font-serif text-3xl font-bold">Deine Bestellung</h1>

        @if (session('shop_status') === 'bestaetigt')
            <p class="mt-4 border-l-4 border-stufe-pfadi bg-white p-3" role="status">Danke, deine Bestellung ist bestätigt. Die Zahlungsaufforderung kommt per Mail.</p>
        @elseif (session('shop_status') === 'storniert')
            <p class="mt-4 border-l-4 border-dpsg-rot bg-white p-3" role="status">Deine Bestellung ist storniert.</p>
        @endif

        <p class="mt-6">Stand: <strong>{{ \App\Models\Shop\Bestellung::STAENDE[$stand] }}</strong> · Bestelldatum (Zahlungsfrist): {{ $bestellung->runde->bestelldatum->format('d.m.Y') }}</p>

        @if ($stand === 'unbestaetigt')
            <form method="post" action="{{ route('shop.bestaetigen', $token) }}" class="mt-4">
                @csrf
                <button type="submit" class="bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">Bestellung bestätigen</button>
            </form>
        @endif

        <div class="mt-6 overflow-x-auto bg-white shadow-md">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-muted"><tr><th class="px-4 py-2">Für</th><th class="px-4 py-2">Artikel</th><th class="px-4 py-2 text-center">Anzahl</th><th class="px-4 py-2 text-right">Preis</th><th class="px-4 py-2">Stand</th></tr></thead>
                <tbody class="divide-y divide-surface-muted">
                    @foreach ($bestellung->positionen as $p)
                        <tr>
                            <td class="px-4 py-2">{{ $p->mitglied }}</td>
                            <td class="px-4 py-2">{{ $p->bezeichnung }}@if ($p->groesse)<span class="text-text-secondary"> ({{ $p->groesse }})</span>@endif @if ($p->nummer)<span class="block text-xs text-text-secondary">Rüsthaus-Nr. {{ $p->nummer }}</span>@endif</td>
                            <td class="px-4 py-2 text-center">{{ $p->menge }}</td>
                            <td class="px-4 py-2 text-right">{{ $p->preis_cent ? \App\Support\Sammelbestellung::euro($p->summeCent()) : '–' }}</td>
                            <td class="px-4 py-2">{{ ! $p->bestaetigt ? 'wird geprüft' : ($p->bezahlt() ? 'bezahlt' : 'Zahlung offen') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @foreach ($bestellung->zahlungen->where('status', '!=', 'storniert') as $zahlung)
            <p class="mt-4 text-sm">Zahlung {{ $zahlung->referenz }}: {{ \App\Support\Zahlungen::euro($zahlung->betrag_cent) }} – {{ \App\Models\Zahlung::STATUS[$zahlung->status] ?? $zahlung->status }}. Kontodaten und GiroCode stehen in der Zahlungsaufforderung.</p>
        @endforeach

        @if (! $bestellung->storniert_am && $bestellung->runde->istOffen() && $bestellung->zahlungen->sum('bezahlt_cent') === 0)
            <form method="post" action="{{ route('shop.stornieren', $token) }}" class="mt-8" onsubmit="return confirm('Bestellung wirklich stornieren?')">
                @csrf
                <button type="submit" class="border border-dpsg-rot px-4 py-2 font-bold text-dpsg-rot hover:bg-dpsg-rot hover:text-white">Bestellung stornieren</button>
            </form>
            <p class="mt-2 text-xs text-text-secondary">Etwas ändern? Storniere und bestelle neu, solange die Runde offen ist – oder schreib uns.</p>
        @endif
    </div>
@endsection
