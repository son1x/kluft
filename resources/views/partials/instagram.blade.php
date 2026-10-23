{{-- Instagram-Einblendung (docs/instagram.md): neueste Beiträge eigener
     Konten, Bilder lokal gespiegelt, kein Embed. Erwartet: $ort
     („startseite“ oder „section:<key>“), optional $class --}}
@php($konten = \App\Models\InstagramKonto::query()->fuer($ort)->get())
@php($beitraege = $konten->isEmpty() ? collect() : \App\Models\InstagramBeitrag::query()->whereIn('instagram_konto_id', $konten->pluck('id'))
    ->with('konto')->orderByDesc('veroeffentlicht_am')->limit($konten->max('anzahl'))->get())
@if ($beitraege->isNotEmpty())
    <section class="{{ $class ?? 'mx-auto mt-20 max-w-6xl px-4' }}" aria-labelledby="instagram-heading-{{ \Illuminate\Support\Str::slug($ort) }}">
        @include('partials.section-heading', ['id' => 'instagram-heading-'.\Illuminate\Support\Str::slug($ort), 'title' => 'Auf Instagram'])
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($beitraege as $beitrag)
                @php($text = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', (string) $beitrag->text)), 120))
                <li>
                    <a href="{{ $beitrag->permalink }}" target="_blank" rel="noopener noreferrer"
                       class="group block bg-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                        <span class="relative block aspect-square overflow-hidden bg-surface-muted">
                            <img src="{{ $beitrag->bildUrl() }}" alt="{{ $text !== '' ? $text : 'Instagram-Beitrag von '.$beitrag->konto?->name }}" loading="lazy" decoding="async"
                                 class="h-full w-full object-cover transition group-hover:scale-105" />
                            @if ($beitrag->art === 'VIDEO')
                                <x-heroicon-s-play-circle class="absolute right-2 top-2 h-7 w-7 text-white drop-shadow" aria-hidden="true" />
                            @elseif ($beitrag->art === 'CAROUSEL_ALBUM')
                                <x-heroicon-s-square-2-stack class="absolute right-2 top-2 h-6 w-6 text-white drop-shadow" aria-hidden="true" />
                            @endif
                        </span>
                        <span class="block p-3 text-sm">
                            @if ($text !== '')<span class="line-clamp-2">{{ $text }}</span>@endif
                            <span class="mt-1 block text-xs text-text-secondary">{{ $beitrag->veroeffentlicht_am->locale('de')->isoFormat('D. MMMM YYYY') }}@if ($konten->count() > 1) · {{ $beitrag->konto?->name }}@endif</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        <p class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            @foreach ($konten->filter(fn ($k) => $k->profilUrl()) as $konto)
                <a href="{{ $konto->profilUrl() }}" target="_blank" rel="noopener noreferrer" class="font-bold text-dpsg-rot underline underline-offset-2 hover:no-underline">
                    {{ '@'.$konto->benutzername }} auf Instagram folgen
                </a>
            @endforeach
        </p>
        <p class="mt-1 text-xs text-text-secondary">Die Bilder liegen auf unserer Website. Erst ein Klick öffnet Instagram.</p>
    </section>
@endif
