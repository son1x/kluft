{{-- Titelbild mit Bildnachweis (F-CMS-14). Erwartet: $media (Media), optional $class --}}
<figure class="{{ $class ?? 'mt-6' }}">
    <img src="{{ $media->url() }}" alt="{{ $media->alt_text }}" class="aspect-[16/9] w-full object-cover" />
    @if ($media->credit)
        <figcaption class="mt-1 text-right text-xs text-text-secondary">Foto: {{ \Illuminate\Support\Str::of($media->credit)->replaceMatches('/^(Foto|Bild)\s*:\s*/i', '') }}</figcaption>
    @endif
</figure>
