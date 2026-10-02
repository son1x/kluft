{{-- Titelbild mit Bildnachweis (F-CMS-14). Erwartet: $media (Media), optional $class --}}
<figure class="{{ $class ?? 'mt-6' }}">
    <x-picture :media="$media" sizes="(min-width: 1024px) 768px, 100vw" loading="eager" class="aspect-[16/9] w-full object-cover" />
    @if ($media->credit)
        <figcaption class="mt-1 text-right text-xs text-text-secondary">Foto: {{ \Illuminate\Support\Str::of($media->credit)->replaceMatches('/^(Foto|Bild)\s*:\s*/i', '') }}</figcaption>
    @endif
</figure>
