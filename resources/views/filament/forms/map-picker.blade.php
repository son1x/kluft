@php($record = $getRecord())
<div>
    <div
        wire:ignore
        data-map-picker
        data-pmtiles="{{ asset(config('dpsg.map.pmtiles')) }}"
        data-bbox="{{ json_encode(config('dpsg.map.bbox')) }}"
        data-lat="{{ $record?->lat }}"
        data-lng="{{ $record?->lng }}"
        style="height: 22rem; width: 100%; border-radius: 0.5rem; overflow: hidden;"
        aria-label="Karte zum Setzen des Standorts"
    ></div>
    <p style="margin-top: 0.5rem; font-size: 0.8rem; opacity: 0.75;">Klick auf die Karte setzt den Standort, der Punkt lässt sich danach verschieben.</p>
</div>
