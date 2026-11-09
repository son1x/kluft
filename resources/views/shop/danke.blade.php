@extends('layouts.app')

{{-- Nach „Verbindlich bestellen“ (SB-134): nächste Schritte als Liste. --}}
@section('seo_title', 'Bestellung abgeschickt – '.config('dpsg.site.name'))

@php($runde = \App\Models\Shop\Runde::offene())

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-16 font-sans">
        <h1 class="font-serif text-3xl font-bold text-dpsg-blau">Fast geschafft</h1>
        <p class="mt-3 text-lg">Bitte bestätige deine Bestellung über den Link in der Mail, die wir dir gerade geschickt haben. Erst dann gilt sie.</p>
        <ol class="mt-8 flex flex-col gap-4">
            @foreach ([
                ['Bestätigen', 'Link in der Mail antippen. Unbestätigte Bestellungen verfallen nach 48 Stunden.'],
                ['Bezahlen', 'Danach kommt die Zahlungsaufforderung mit Bankverbindung und GiroCode'.($runde ? ', zu zahlen bis '.$runde->bestelldatum->format('d.m.Y') : '').'.'],
                ['Abholen', 'Wir bestellen gesammelt beim Rüsthaus und geben die Sachen in der Gruppenstunde aus. Wenn sie da sind, bekommst du Bescheid.'],
            ] as $i => [$titel, $text])
                <li class="flex gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-dpsg-blau font-bold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    <span><strong class="block">{{ $titel }}</strong><span class="text-[15px] text-text-secondary">{{ $text }}</span></span>
                </li>
            @endforeach
        </ol>
        <p class="mt-6 text-[15px] text-text-secondary">Stornieren kannst du über den Link in der Mail bis zum Bestellschluss, also bis wir beim Rüsthaus bestellen.</p>
        <p class="mt-6"><a href="{{ route('shop.index') }}" class="inline-flex min-h-11 items-center font-semibold text-dpsg-blau underline">Zurück zur Sammelbestellung</a></p>
    </div>
@endsection
