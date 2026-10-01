{{-- Erwartet: $g (Gruppierung), optional $headingId --}}
@php($isBezirk = $g->typ === 'bezirk')
<article @class(['bg-white p-4 shadow-md', 'border-t-4 border-dpsg-blau' => $isBezirk])>
    @if ($g->nummer)
        <p class="font-mono text-xs text-text-secondary" title="DPSG-Nummer: Diözesanverband-Bezirk-Stamm">{{ $g->nummer }}</p>
    @endif
    @if ($isBezirk)
        <h2 @isset($headingId) id="{{ $headingId }}" @endisset class="font-serif text-xl font-bold text-dpsg-blau">{{ $g->displayName() }}</h2>
    @else
        <h3 class="font-serif text-lg font-bold">{{ $g->displayName() }}</h3>
    @endif

    @if ($g->strasse || ($g->ort && ! str_contains($g->name, $g->ort)))
        <p class="mt-1 text-sm">
            @if ($g->strasse){{ $g->strasse }}<br />@endif
            {{ trim($g->plz.' '.$g->ort) }}
        </p>
    @endif

    @if ($stufen = $g->stufen())
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach ($stufen as $stufe)
                @include('partials.stufe-badge', ['stage' => $stufe])
            @endforeach
        </div>
    @endif

    @if ($g->beschreibung)
        <p class="mt-2 text-sm">{{ $g->beschreibung }}</p>
    @endif

    <ul class="mt-2 space-y-1 text-sm">
        @if ($person = $g->publicAnsprechperson())
            <li>Ansprechperson: {{ $person }}</li>
        @endif
        @if ($g->kontakt_email)
            <li><a href="mailto:{{ $g->kontakt_email }}" class="text-dpsg-rot underline">{{ $g->kontakt_email }}</a></li>
        @endif
        @if ($g->kontakt_telefon)
            <li><a href="tel:{{ preg_replace('/[^+\d]/', '', $g->kontakt_telefon) }}" class="text-dpsg-rot underline">{{ $g->kontakt_telefon }}</a></li>
        @endif
        @if ($url = $g->safeUrl('website_url'))
            <li><a href="{{ $url }}" target="_blank" rel="noopener" class="text-dpsg-rot underline">Website</a></li>
        @endif
        @if ($url = $g->safeUrl('social_url'))
            <li><a href="{{ $url }}" target="_blank" rel="noopener" class="text-dpsg-rot underline">Social Media</a></li>
        @endif
    </ul>

    @if ($g->hasPosition())
        {{-- Erst per JavaScript sichtbar (ohne Karte sinnlos). --}}
        <button type="button" data-show-on-map="{{ $g->id }}" hidden class="mt-3 border border-dpsg-rot px-3 py-1 text-sm text-dpsg-rot hover:bg-dpsg-rot hover:text-white">Auf der Karte zeigen</button>
        @if ($g->hasApproximatePosition())
            <p class="mt-2 text-xs text-text-secondary">Standort ungefähr (Ortsmitte)</p>
        @endif
    @else
        <p class="mt-2 text-xs text-text-secondary">Standort noch nicht erfasst</p>
    @endif
</article>
