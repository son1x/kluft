{{-- Bild mit WebP-Varianten (F-CMS-04). Fällt ohne Varianten aufs Original zurück. --}}
@props(['media', 'sizes' => '100vw', 'alt' => null, 'loading' => 'lazy'])
<picture>
    @if ($srcset = $media->webpSrcset())
        <source type="image/webp" srcset="{{ $srcset }}" sizes="{{ $sizes }}" />
    @endif
    <img src="{{ $media->url() }}" alt="{{ $alt ?? $media->alt_text }}" loading="{{ $loading }}" decoding="async"
        @if ($media->width && $media->height) width="{{ $media->width }}" height="{{ $media->height }}" @endif
        {{ $attributes }} />
</picture>
