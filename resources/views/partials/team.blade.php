{{-- Personenvorstellung mit Gruppenfoto (F-CMS-12). Erwartet: $people, $teamPhoto --}}
@php
    $spots = $teamPhoto?->spots->filter(fn ($s) => $s->person?->consent && $s->person->active) ?? collect();
    // Personen eines anderen Gremiums (z. B. Bildungsreferent*innen auf dem
    // Foto der Diözesanleitung) verlinken auf deren Seite.
    $onThisPage = $people->pluck('id')->all();
    $href = fn ($person) => in_array($person->id, $onThisPage, true) ? '#'.$person->anchor() : $person->pageUrl();
@endphp
<section class="mx-auto max-w-6xl px-4" x-data="{ active: null, names: false }" aria-label="Personen">
    @if ($teamPhoto && $teamPhoto->media)
        <figure class="mb-12">
            @if ($teamPhoto->title)
                <h2 class="mb-4 font-serif text-2xl font-bold">{{ $teamPhoto->title }}</h2>
            @endif
            <div class="relative overflow-hidden rounded-lg bg-surface-muted shadow-md">
                <x-picture :media="$teamPhoto->media" sizes="(min-width: 1152px) 1120px, 100vw" loading="eager" class="block h-auto w-full" />
                @foreach ($spots as $spot)
                    @php($a = $spot->person->anchor())
                    <a href="{{ $href($spot->person) }}"
                        class="group absolute block -translate-x-1/2 -translate-y-1/2 focus:outline-none"
                        style="left: {{ $spot->x }}%; top: {{ $spot->y }}%; width: 8.5%; aspect-ratio: 1;"
                        aria-label="{{ $spot->person->name }}{{ $spot->person->role ? ' – '.$spot->person->role : '' }}{{ in_array($spot->person->id, $onThisPage, true) ? '' : ' ('.$spot->person->gremiumLabel().')' }}"
                        x-on:mouseenter="active = '{{ $a }}'" x-on:mouseleave="active = null"
                        x-on:focus="active = '{{ $a }}'" x-on:blur="active = null">
                        <span class="absolute inset-0 rounded-full border-[3px] transition duration-150"
                            x-bind:class="active === '{{ $a }}' ? 'border-white shadow-[0_0_0_4px_rgba(0,48,86,0.55)]' : 'border-transparent'"></span>
                        <span class="pointer-events-none absolute left-1/2 top-full mt-1 -translate-x-1/2 whitespace-nowrap rounded bg-dpsg-blau/90 px-2 py-0.5 text-center text-[11px] font-bold leading-tight text-white shadow sm:text-xs"
                            x-show="names || active === '{{ $a }}'" x-cloak>
                            <span x-show="active !== '{{ $a }}'">{{ \Illuminate\Support\Str::before($spot->person->name, ' ') }}</span>
                            <span x-show="active === '{{ $a }}'">{{ $spot->person->name }}@if ($spot->person->role)<br /><span class="font-normal">{{ $spot->person->role }}@unless (in_array($spot->person->id, $onThisPage, true)) · {{ $spot->person->gremiumLabel() }}@endunless</span>@endif</span>
                        </span>
                    </a>
                @endforeach
            </div>
            <figcaption class="mt-3 flex flex-wrap items-start justify-between gap-3 text-sm text-text-secondary">
                <span class="max-w-3xl">
                    @if ($spots->isNotEmpty())
                        Von links:
                        @foreach ($spots as $spot)
                            @php($a = $spot->person->anchor())
                            <a href="{{ $href($spot->person) }}" class="text-text underline decoration-dpsg-mittelbeige underline-offset-2 hover:text-dpsg-rot"
                                x-on:mouseenter="active = '{{ $a }}'" x-on:mouseleave="active = null">{{ $spot->person->name }}</a>{{ $loop->last ? '.' : ',' }}
                        @endforeach
                    @endif
                    @if ($teamPhoto->note) {{ $teamPhoto->note }} @endif
                    @if ($teamPhoto->media->credit) <span class="whitespace-nowrap">Foto: {{ $teamPhoto->media->credit }}</span> @endif
                </span>
                @if ($spots->isNotEmpty())
                    <button type="button" class="shrink-0 border border-dpsg-blau px-3 py-1 font-bold text-dpsg-blau hover:bg-dpsg-blau hover:text-white"
                        x-on:click="names = ! names" x-bind:aria-pressed="names.toString()"
                        x-text="names ? 'Namen ausblenden' : 'Namen einblenden'">Namen einblenden</button>
                @endif
            </figcaption>
        </figure>
    @endif

    @foreach ($people->groupBy(fn ($p) => $p->subgroup ?? '') as $subgroup => $members)
        <div class="mb-10">
            @if ($subgroup !== '')
                <h2 class="mb-4 font-serif text-xl font-bold">{{ $subgroup }}</h2>
            @endif
            <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($members as $person)
                    @php($a = $person->anchor())
                    <li id="{{ $a }}" class="scroll-mt-28 rounded-lg bg-white p-5 shadow-md ring-dpsg-blau transition target:ring-4"
                        x-bind:class="active === '{{ $a }}' && 'ring-4'"
                        x-on:mouseenter="active = '{{ $a }}'" x-on:mouseleave="active = null">
                        <div class="flex items-center gap-4">
                            @include('partials.person-avatar', ['person' => $person])
                            <div>
                                <h3 class="font-serif text-lg font-bold leading-tight">{{ $person->name }}</h3>
                                @if ($person->role)<p class="text-sm text-text-secondary">{{ $person->role }}</p>@endif
                                @if ($person->publicEmail())<a href="mailto:{{ $person->publicEmail() }}" class="text-sm break-all text-dpsg-rot underline">{{ $person->publicEmail() }}</a>@endif
                            </div>
                        </div>
                        @if ($person->bio)
                            @php($paragraphs = preg_split('/\n\s*\n/', trim($person->bio)))
                            @if (mb_strlen($person->bio) > 260)
                                <details class="group mt-4 text-sm">
                                    <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                                        <span class="block group-open:hidden">{{ \Illuminate\Support\Str::limit($paragraphs[0], 200) }}</span>
                                        <span class="mt-1 inline-block font-bold text-dpsg-rot underline group-open:hidden">Mehr lesen</span>
                                        <span class="hidden font-bold text-dpsg-rot underline group-open:inline">Weniger anzeigen</span>
                                    </summary>
                                    @foreach ($paragraphs as $paragraph)
                                        <p class="mt-2">{!! nl2br(e($paragraph)) !!}</p>
                                    @endforeach
                                </details>
                            @else
                                @foreach ($paragraphs as $paragraph)
                                    <p class="mt-3 text-sm">{!! nl2br(e($paragraph)) !!}</p>
                                @endforeach
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</section>
