{{-- Hinweise unter dem Titelbild der Startseite (App\Models\StartseitenHinweis).
     Abstufung nach Wichtigkeit: Rot (sehr wichtig) › Beige (wichtig) › Weiß (dezent). --}}
@if ($hinweise->isNotEmpty())
    @php($stile = [
        'rot' => ['flaeche' => 'border-dpsg-rot bg-white', 'titel' => 'text-dpsg-rot', 'knopf' => 'bg-dpsg-rot text-white hover:bg-dpsg-blau', 'link' => 'text-dpsg-rot', 'symbol' => 'text-dpsg-rot'],
        'beige' => ['flaeche' => 'border-dpsg-blau bg-dpsg-hellbeige', 'titel' => 'text-dpsg-blau', 'knopf' => 'bg-dpsg-blau text-white hover:bg-dpsg-rot', 'link' => 'text-dpsg-blau', 'symbol' => 'text-dpsg-blau'],
        'weiss' => ['flaeche' => 'border-dpsg-mittelbeige bg-white', 'titel' => 'text-text', 'knopf' => 'border border-dpsg-blau bg-white text-dpsg-blau hover:bg-dpsg-blau hover:text-white', 'link' => 'text-dpsg-blau', 'symbol' => 'text-text-secondary'],
    ])
    <section class="mx-auto mt-8 max-w-6xl space-y-4 px-4" aria-label="Hinweise">
        @foreach ($hinweise as $hinweis)
            @php($stil = $stile[$hinweis->farbe] ?? $stile['weiss'])
            @php($ziel = filled($hinweis->link_url) ? (str_starts_with($hinweis->link_url, '/') ? url($hinweis->link_url) : $hinweis->link_url) : null)
            @php($extern = $hinweis->istExtern())
            @if ($hinweis->art === 'zeile')
                {{-- Dezent: schmale Zeile mit Symbol, Text und Link. --}}
                <article class="flex flex-wrap items-center gap-x-3 gap-y-1 border-l-4 px-4 py-2 text-sm shadow-sm {{ $stil['flaeche'] }}">
                    <x-heroicon-o-information-circle class="h-5 w-5 shrink-0 {{ $stil['symbol'] }}" aria-hidden="true" />
                    <h2 class="font-bold">{{ $hinweis->titel }}</h2>
                    @if (filled($hinweis->text))
                        <p class="text-text-secondary">{{ $hinweis->text }}</p>
                    @endif
                    @if ($ziel)
                        <a href="{{ $ziel }}" @if ($extern) target="_blank" rel="noopener" @endif
                           class="inline-flex min-h-11 items-center gap-1 font-bold underline sm:ml-auto {{ $stil['link'] }}">
                            {{ $hinweis->link_text ?: 'Mehr erfahren' }}
                            <x-heroicon-o-arrow-right class="h-4 w-4" aria-hidden="true" />
                            @if ($extern)<span class="sr-only">(öffnet eine andere Website in einem neuen Tab)</span>@endif
                        </a>
                    @endif
                </article>
                @continue
            @endif
            <article class="flex flex-col gap-4 border-l-8 p-6 shadow-md sm:flex-row sm:items-center sm:justify-between sm:gap-8 {{ $stil['flaeche'] }}">
                <div class="min-w-0">
                    <h2 class="font-serif text-xl font-bold {{ $stil['titel'] }}">{{ $hinweis->titel }}</h2>
                    @if (filled($hinweis->text))
                        <p class="mt-2 max-w-3xl">{{ $hinweis->text }}</p>
                    @endif
                </div>
                @if ($ziel)
                    <a href="{{ $ziel }}" @if ($extern) target="_blank" rel="noopener" @endif
                       class="inline-flex min-h-11 shrink-0 items-center gap-2 self-start px-5 py-2 font-bold sm:self-center {{ $stil['knopf'] }}">
                        {{ $hinweis->link_text ?: 'Mehr erfahren' }}
                        @if ($extern)
                            <x-heroicon-o-arrow-top-right-on-square class="h-5 w-5" aria-hidden="true" />
                            <span class="sr-only">(öffnet eine andere Website in einem neuen Tab)</span>
                        @endif
                    </a>
                @endif
            </article>
        @endforeach
    </section>
@endif
