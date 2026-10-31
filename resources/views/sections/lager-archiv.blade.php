{{-- Hinweis für zurückliegende Lager (config dpsg.sections.<lager>.archiv). Erwartet: $config --}}
@if ($config['archiv'] ?? false)
    <div class="border-b border-(--lager-rand,#d0d4e3) bg-(--lager-flaeche,#f6f6f9)" role="note">
        <p class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-sm">
            <span class="lager-pill lager-pill-akzent">Archiv</span>
            <span>Das {{ $config['kind'] ?? 'Lager' }} {{ $config['jahr'] ?? '' }} liegt zurück. Diese Seiten zeigen den Stand von damals. Anmeldung und Kontaktadressen gelten nicht mehr.</span>
            @if ($uebersicht = \App\Support\Lager::uebersichtUrl())
                <a href="{{ $uebersicht }}" class="font-semibold underline underline-offset-2">Alle Diözesanlager</a>
            @endif
        </p>
    </div>
@endif
