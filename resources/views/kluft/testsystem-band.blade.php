{{-- Testsystem-Band im Backend (Filament hat kein Tailwind der Website, daher Inline-Stile). --}}
<div role="note" style="background:#ffed00;color:#1c1f26;text-align:center;padding:.4rem 1rem;font-size:.875rem;position:relative;z-index:50">
    <strong>Testsystem.</strong> Änderungen hier gehen nicht auf die Website.
    @if ($live = \App\Support\Testsystem::liveUrl())
        Inhalte bitte auf <a href="{{ $live }}/admin" style="font-weight:700;text-decoration:underline;color:inherit">{{ preg_replace('#^https://#', '', $live) }}</a> pflegen.
    @endif
</div>
