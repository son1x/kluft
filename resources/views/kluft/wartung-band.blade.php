{{-- Wartungsmodus an (App\Support\Wartung): Hinweis für Angemeldete, auf der Website und im Backend (Inline-Stile, Filament hat kein Tailwind der Website). --}}
<div role="note" style="background:#a50034;color:#fff;text-align:center;padding:.4rem 1rem;font-size:.875rem;position:relative;z-index:50">
    <strong>Wartungsmodus ist an.</strong> Besucher*innen sehen die Wartungsseite.
    @if (auth()->user()?->isAdmin())
        <a href="{{ \App\Filament\Pages\Wartungsmodus::getUrl() }}" style="font-weight:700;text-decoration:underline;color:inherit">Ausschalten</a>
    @endif
</div>
