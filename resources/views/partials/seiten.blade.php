{{-- Seitennavigation der Website (statt Laravels Standard): deutsch, ohne
     aria-label an span (axe „aria-prohibited-attr“), aktuelle Seite mit
     aria-current, Ziele mindestens 44 px. Als Standard gesetzt in
     AppServiceProvider (Paginator::defaultView). --}}
@if ($paginator->hasPages())
    @php($knopf = 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border px-3 text-sm font-bold')
    <nav class="mt-10" aria-label="Seiten">
        <p class="mb-3 text-sm text-text-secondary">
            Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }}
            @if (method_exists($paginator, 'total')) · {{ $paginator->total() }} insgesamt @endif
        </p>
        <ul class="flex flex-wrap items-center gap-2">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $knopf }} border-dpsg-mittelbeige text-text-secondary opacity-60" aria-disabled="true">‹ Zurück</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $knopf }} border-dpsg-mittelbeige bg-white text-dpsg-blau hover:border-dpsg-blau">‹ Zurück</a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="{{ $knopf }} border-transparent text-text-secondary">…</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $seite => $url)
                        <li class="hidden sm:block">
                            @if ($seite == $paginator->currentPage())
                                <span class="{{ $knopf }} border-dpsg-blau bg-dpsg-blau text-white" aria-current="page">{{ $seite }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $knopf }} border-dpsg-mittelbeige bg-white text-dpsg-blau hover:border-dpsg-blau">
                                    <span class="sr-only">Seite </span>{{ $seite }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $knopf }} border-dpsg-mittelbeige bg-white text-dpsg-blau hover:border-dpsg-blau">Weiter ›</a>
                @else
                    <span class="{{ $knopf }} border-dpsg-mittelbeige text-text-secondary opacity-60" aria-disabled="true">Weiter ›</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
