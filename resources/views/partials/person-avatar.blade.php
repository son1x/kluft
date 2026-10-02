{{-- Rundes Personenbild; ohne (freigegebenes) Foto ein Kreis mit Initialen
     in der Stufenfarbe. Erwartet: $person, optional $size (Tailwind-Klassen). --}}
@php($size = $size ?? 'h-20 w-20 text-2xl')
@if ($person->photo && $person->consent)
    <x-picture :media="$person->photo" alt="" sizes="160px" class="{{ $size }} shrink-0 rounded-full object-cover" />
@else
    <span aria-hidden="true" @class([
        $size, 'flex shrink-0 select-none items-center justify-center rounded-full font-serif font-bold tracking-wide',
        'bg-dpsg-blau text-white' => ! $person->stage,
        'bg-stufe-biber text-white' => $person->stage === 'biber',
        'bg-stufe-woelflinge text-text' => $person->stage === 'woelflinge',
        'bg-stufe-jupfi text-white' => $person->stage === 'jupfi',
        'bg-stufe-pfadi text-white' => $person->stage === 'pfadi',
        'bg-stufe-rover text-white' => $person->stage === 'rover',
    ])>{{ $person->initials() }}</span>
@endif
