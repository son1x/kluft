{{-- Logo des Newsletters (config dpsg.newsletter.logo, .png + .webp);
     ohne Logo der Name als Schriftzug. --}}
@php($newsletter = config('dpsg.newsletter'))
@if ($newsletter['logo'] ?? null)
    <picture>
        <source type="image/webp" srcset="{{ asset($newsletter['logo'].'.webp') }}" />
        <img src="{{ asset($newsletter['logo'].'.png') }}" alt="{{ $alt ?? $newsletter['logo_alt'] }}" width="{{ $newsletter['logo_size'][0] }}" height="{{ $newsletter['logo_size'][1] }}" class="{{ $class }}" @if ($lazy ?? false) loading="lazy" @endif />
    </picture>
@else
    <span class="block font-serif text-2xl font-bold text-dpsg-blau">{{ $newsletter['name'] }}</span>
@endif
