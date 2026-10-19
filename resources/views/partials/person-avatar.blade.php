{{-- Rundes Personenbild; ohne (freigegebenes) Foto ein Kreis mit Initialen
     in der Stufenfarbe bzw. mit Muster (config dpsg.person_badges). Erwartet: $person, optional $size (Tailwind-Klassen). --}}
@php($size = $size ?? 'h-24 w-24 text-3xl')
@if ($person->photo && $person->consent)
    <x-picture :media="$person->photo" alt="" sizes="192px" class="{{ $size }} shrink-0 rounded-full object-cover" />
@else
    @php($badge = $person->badge ?? $person->stage)
    <span aria-hidden="true" @class([
        $size, 'flex shrink-0 select-none items-center justify-center rounded-full font-serif font-bold tracking-wide',
        'bg-dpsg-blau text-white' => ! $badge,
        'bg-stufe-biber text-(--stufe-ink-biber)' => $badge === 'biber',
        'bg-stufe-woelflinge text-(--stufe-ink-woelflinge)' => $badge === 'woelflinge',
        'bg-stufe-jupfi text-(--stufe-ink-jupfi)' => $badge === 'jupfi',
        'bg-stufe-pfadi text-(--stufe-ink-pfadi)' => $badge === 'pfadi',
        'bg-stufe-rover text-(--stufe-ink-rover)' => $badge === 'rover',
        'avatar-inklusion' => $badge === 'inklusion',
        'avatar-bolivien' => $badge === 'bolivien',
    ])>
        @if ($badge === 'bolivien')
            {{-- Auf den Streifen bliebe Weiß auf Weiß unlesbar: dunkle Scheibe dahinter. --}}
            <span class="flex h-[62%] w-[62%] items-center justify-center rounded-full bg-black/45">{{ $person->initials() }}</span>
        @else
            {{ $person->initials() }}
        @endif
    </span>
@endif
