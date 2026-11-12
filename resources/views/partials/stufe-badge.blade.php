{{-- Erwartet: $stage (nullable string) --}}
@if ($stage)
    {{-- Textfarbe je Stufe nach CD-Leitfaden-Kontrastregel (Pflichtenheft
         7.2): Wölflinge-Orange erreicht nur 3,0:1 mit Weiß, braucht
         dunklen Text; ebenso eine abweichende helle Stufenfarbe der
         Instanz (z. B. gelbe Biber). Steht in --stufe-ink-<stufe>. --}}
    <span @class([
        'inline-block rounded px-2 py-0.5 text-xs font-sans font-medium',
        'bg-stufe-biber text-(--stufe-ink-biber)' => $stage === 'biber',
        'bg-stufe-woelflinge text-(--stufe-ink-woelflinge)' => $stage === 'woelflinge',
        'bg-stufe-jupfi text-(--stufe-ink-jupfi)' => $stage === 'jupfi',
        'bg-stufe-pfadi text-(--stufe-ink-pfadi)' => $stage === 'pfadi',
        'bg-stufe-rover text-(--stufe-ink-rover)' => $stage === 'rover',
        'bg-farbe-leiter text-text' => $stage === \App\Models\Event::LEITENDE,
    ])>
        {{ match ($stage) {
            'biber' => 'Biber',
            'woelflinge' => 'Wölflinge',
            'jupfi' => 'Jungpfadfinder',
            'pfadi' => 'Pfadfinder',
            'rover' => 'Rover',
            \App\Models\Event::LEITENDE => 'Leiter*innen',
            default => $stage,
        } }}
    </span>
@endif
