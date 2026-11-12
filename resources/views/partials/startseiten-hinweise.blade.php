{{-- Hinweise unter dem Titelbild der Startseite (App\Models\StartseitenHinweis). --}}
@if ($hinweise->isNotEmpty())
    <section class="mx-auto mt-8 max-w-6xl space-y-4 px-4" aria-label="Hinweise">
        @foreach ($hinweise as $hinweis)
            @php($farbe = $hinweis->farbe)
            @php($ziel = filled($hinweis->link_url) ? (str_starts_with($hinweis->link_url, '/') ? url($hinweis->link_url) : $hinweis->link_url) : null)
            @php($extern = $hinweis->istExtern())
            @if ($hinweis->art === 'zeile')
                {{-- Dezent: schmale Zeile mit Symbol, Text und Link. --}}
                <article @class([
                    'flex flex-wrap items-center gap-x-3 gap-y-1 border-l-4 px-4 py-2 text-sm shadow-sm',
                    'border-dpsg-rot bg-white' => $farbe === 'rot',
                    'border-dpsg-blau bg-white' => $farbe === 'blau',
                    'border-dpsg-mittelbeige bg-dpsg-hellbeige' => ! in_array($farbe, ['rot', 'blau'], true),
                ])>
                    <x-heroicon-o-information-circle @class(['h-5 w-5 shrink-0', 'text-dpsg-rot' => $farbe === 'rot', 'text-dpsg-blau' => $farbe !== 'rot']) aria-hidden="true" />
                    <h2 class="font-bold">{{ $hinweis->titel }}</h2>
                    @if (filled($hinweis->text))
                        <p class="text-text-secondary">{{ $hinweis->text }}</p>
                    @endif
                    @if ($ziel)
                        <a href="{{ $ziel }}" @if ($extern) target="_blank" rel="noopener" @endif
                           @class(['inline-flex min-h-11 items-center gap-1 font-bold underline sm:ml-auto', 'text-dpsg-rot' => $farbe === 'rot', 'text-dpsg-blau' => $farbe !== 'rot'])>
                            {{ $hinweis->link_text ?: 'Mehr erfahren' }}
                            <x-heroicon-o-arrow-right class="h-4 w-4" aria-hidden="true" />
                            @if ($extern)<span class="sr-only">(öffnet eine andere Website in einem neuen Tab)</span>@endif
                        </a>
                    @endif
                </article>
                @continue
            @endif
            <article @class([
                'flex flex-col gap-4 border-l-8 p-6 shadow-md sm:flex-row sm:items-center sm:justify-between sm:gap-8',
                'border-dpsg-rot bg-white' => $farbe === 'rot',
                'border-dpsg-blau bg-white' => $farbe === 'blau',
                'border-dpsg-mittelbeige bg-dpsg-hellbeige' => ! in_array($farbe, ['rot', 'blau'], true),
            ])>
                <div class="min-w-0">
                    <h2 @class(['font-serif text-xl font-bold', 'text-dpsg-rot' => $farbe === 'rot', 'text-dpsg-blau' => $farbe !== 'rot'])>{{ $hinweis->titel }}</h2>
                    @if (filled($hinweis->text))
                        <p class="mt-2 max-w-3xl">{{ $hinweis->text }}</p>
                    @endif
                </div>
                @if ($ziel)
                    <a href="{{ $ziel }}"
                       @if ($extern) target="_blank" rel="noopener" @endif
                       @class([
                           'inline-flex min-h-11 shrink-0 items-center gap-2 self-start px-5 py-2 font-bold text-white sm:self-center',
                           'bg-dpsg-rot hover:bg-dpsg-blau' => $farbe === 'rot',
                           'bg-dpsg-blau hover:bg-dpsg-rot' => $farbe !== 'rot',
                       ])>
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
