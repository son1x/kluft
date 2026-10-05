@extends('layouts.app')

@section('seo_title', $title.' – '.config('dpsg.site.name'))
@section('seo_description', $intro?->seo_description ?: 'Methoden, Formulare und Arbeitshilfen des '.config('dpsg.site.name_genitive').' zum Herunterladen.')

{{-- Download-Seite: Karten je Kategorie; Suche und Filter laufen im Browser
     (ohne JavaScript ist einfach alles sichtbar).
     Erwartet: $title, $intro (Page|null), $groups (Kategorie => Downloads), $categories, $active --}}
@php
    $typeColors = ['pdf' => 'bg-[#c62828]', 'text' => 'bg-[#1f5bb5]', 'tabelle' => 'bg-[#1e7a45]', 'praesentation' => 'bg-[#c75b12]', 'zip' => 'bg-[#5c6670]', 'bild' => 'bg-[#00739f]'];
    $index = $groups->flatten()->map(fn ($d) => ['k' => $d->category, 't' => mb_strtolower($d->title.' '.$d->description.' '.collect($d->tags())->pluck(0)->implode(' '))])->values();
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10"
         x-data="{ q: '', kat: @js($active), index: @js($index),
                   passt(k, t) { return (this.kat === '' || this.kat === k) && (this.q.trim() === '' || t.includes(this.q.trim().toLowerCase())) },
                   treffer(k) { return this.index.filter(i => i.k === k && this.passt(i.k, i.t)).length },
                   gesamt() { return this.index.filter(i => this.passt(i.k, i.t)).length } }">
        <h1 class="font-serif text-3xl font-bold">{{ $intro?->title ?: $title }}</h1>

        @if ($intro && filled(strip_tags((string) $intro->body)))
            <div class="prose mt-4 max-w-3xl">
                {!! str($intro->body)->before('[TODO')->sanitizeHtml()->glossar() !!}
            </div>
        @endif

        @if ($groups->isEmpty())
            <p class="mt-8 text-text-secondary">Hier findest du bald Methoden, Formulare und Arbeitshilfen zum Herunterladen.</p>
        @else
            <div class="mt-8 flex flex-wrap items-center gap-3" x-cloak>
                <label for="download-suche" class="sr-only">Downloads durchsuchen</label>
                <input id="download-suche" type="search" x-model="q" placeholder="Suchen, z. B. „Kennenlernspiel“ oder „Zuschuss“"
                       class="min-w-64 flex-1 rounded-lg border border-dpsg-mittelbeige bg-white px-4 py-2.5" />
                <div class="flex flex-wrap gap-2" role="group" aria-label="Nach Kategorie filtern">
                    <button type="button" @click="kat = ''" :aria-pressed="(kat === '').toString()"
                            :class="kat === '' ? 'bg-dpsg-blau text-white border-dpsg-blau' : 'bg-white text-dpsg-blau border-dpsg-mittelbeige'"
                            class="rounded-full border px-4 py-1.5 text-sm font-bold">Alle ({{ $groups->flatten()->count() }})</button>
                    @foreach ($groups as $key => $items)
                        <button type="button" @click="kat = @js($key)" :aria-pressed="(kat === @js($key)).toString()"
                                :class="kat === @js($key) ? 'bg-dpsg-blau text-white border-dpsg-blau' : 'bg-white text-dpsg-blau border-dpsg-mittelbeige'"
                                class="rounded-full border px-4 py-1.5 text-sm font-bold">{{ $categories[$key] ?? \Illuminate\Support\Str::headline($key) }}</button>
                    @endforeach
                </div>
            </div>
            <p class="mt-6 text-text-secondary" x-cloak x-show="gesamt() === 0">Nichts gefunden. Probier einen anderen Suchbegriff oder „Alle“.</p>

            @foreach ($groups as $key => $items)
                <section id="{{ $key }}" class="mt-10 scroll-mt-24" aria-labelledby="kategorie-{{ $key }}" x-show="treffer(@js($key)) > 0">
                    <h2 id="kategorie-{{ $key }}" class="flex flex-wrap items-baseline gap-x-3 font-serif text-2xl font-bold">
                        {{ $categories[$key] ?? \Illuminate\Support\Str::headline($key) }}
                        <span class="font-sans text-sm font-normal text-text-secondary">{{ $items->count() }} {{ $items->count() === 1 ? 'Datei' : 'Dateien' }}</span>
                    </h2>
                    <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($items as $download)
                            @php($text = mb_strtolower($download->title.' '.$download->description.' '.collect($download->tags())->pluck(0)->implode(' ')))
                            <li x-show="passt(@js($download->category), @js($text))">
                                <a href="{{ $download->url() }}" @if (in_array($download->extension(), ['pdf', 'jpg', 'jpeg', 'png'], true)) target="_blank" rel="noopener" @endif
                                   class="group flex h-full gap-4 rounded-lg bg-white p-4 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                                    <span class="relative flex h-14 w-11 shrink-0 items-end justify-center rounded-md pb-2 text-xs font-bold text-white {{ $typeColors[$download->family()] ?? $typeColors['zip'] }}" aria-hidden="true">
                                        {{ $download->badge() }}
                                        <span class="absolute right-0 top-0 h-3 w-3 rounded-bl-sm bg-black/25"></span>
                                    </span>
                                    <span class="flex min-w-0 flex-1 flex-col">
                                        <span class="font-bold leading-tight text-dpsg-blau group-hover:text-dpsg-rot">
                                            {{ $download->title }}
                                            @if ($download->isNew())<span class="ml-1 rounded bg-dpsg-yellow px-1.5 py-0.5 align-middle text-[11px] font-bold text-text">NEU</span>@endif
                                        </span>
                                        @if ($download->description)<span class="mt-1 text-sm text-text-secondary">{{ $download->description }}</span>@endif
                                        @if ($tags = $download->tags())
                                            <span class="mt-2 flex flex-wrap gap-1.5">
                                                @foreach ($tags as [$label, $class])
                                                    <span class="rounded px-2 py-0.5 text-xs font-bold {{ $class }}">{{ $label }}</span>
                                                @endforeach
                                            </span>
                                        @endif
                                        <span class="mt-auto flex items-end justify-between gap-2 pt-3">
                                            <span class="text-xs text-text-secondary">{{ $download->meta() }}</span>
                                            <span class="inline-flex shrink-0 items-center gap-1 rounded-md bg-dpsg-rot px-3 py-1.5 text-sm font-bold text-white">
                                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" aria-hidden="true" />
                                                {{ in_array($download->extension(), ['pdf', 'jpg', 'jpeg', 'png'], true) ? 'Öffnen' : 'Download' }}
                                            </span>
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </div>
@endsection
