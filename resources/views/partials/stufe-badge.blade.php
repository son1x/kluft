{{-- Erwartet: $stage (nullable string) --}}
@if ($stage)
    {{-- Textfarbe je Stufe nach CD-Leitfaden-Kontrastregel (Pflichtenheft
         7.2): Wölflinge-Orange erreicht nur 3,0:1 mit Weiß, braucht
         dunklen Text; alle anderen Stufenfarben vertragen weiße Schrift. --}}
    <span @class([
        'inline-block rounded px-2 py-0.5 text-xs font-sans font-medium',
        'bg-stufe-biber text-white' => $stage === 'biber',
        'bg-stufe-woelflinge text-text' => $stage === 'woelflinge',
        'bg-stufe-jupfi text-white' => $stage === 'jupfi',
        'bg-stufe-pfadi text-white' => $stage === 'pfadi',
        'bg-stufe-rover text-white' => $stage === 'rover',
    ])>
        {{ match ($stage) {
            'biber' => 'Biber',
            'woelflinge' => 'Wölflinge',
            'jupfi' => 'Jungpfadfinder',
            'pfadi' => 'Pfadfinder',
            'rover' => 'Rover',
            default => $stage,
        } }}
    </span>
@endif
