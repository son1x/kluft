{{-- Statisches Bild aus public/ mit WebP-Fassung, falls vorhanden (z. B.
     images/stufen/biber.png + biber.webp). <picture> mit display: contents,
     damit das <img> im Layout bleibt, wo es war. --}}
@props(['src', 'alt' => ''])
@php($webp = preg_replace('/\.(png|jpe?g)$/i', '.webp', $src))
<picture class="contents">
    @if ($webp !== $src && is_file(public_path($webp)))
        <source type="image/webp" srcset="{{ asset($webp) }}" />
    @endif
    <img src="{{ asset($src) }}" alt="{{ $alt }}" {{ $attributes }} />
</picture>
