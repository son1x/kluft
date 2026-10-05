@extends('layouts.app')

{{-- Bezirksfilter und „Bezirke und …“ nur, wenn es mehr als einen Bezirk gibt (beim Bezirk selbst nicht). --}}
@php($mehrereBezirke = $bezirke->count() > 1)
@section('seo_title', config('dpsg.directory.title').' – '.config('dpsg.site.name'))
@section('seo_description', 'Wo finde ich meinen Stamm? Alle '.($mehrereBezirke ? 'Bezirke und ' : '').'Stämme der DPSG im '.config('dpsg.site.region').' mit Adresse, Kontakt und Karte.')

@push('head')
    @vite('resources/js/map.js')
@endpush

@section('content')
    @php($stages = config('dpsg.stages'))
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">{{ config('dpsg.directory.title') }}</h1>
        @if ($intro && trim(strip_tags($intro->body)) !== '')
            <div class="prose mt-3 max-w-3xl text-text-secondary">{!! str($intro->body)->sanitizeHtml() !!}</div>
        @else
            <p class="mt-3 max-w-3xl text-text-secondary">
                Wo finde ich meinen Stamm? Hier findest du alle {{ $mehrereBezirke ? 'Bezirke und ' : '' }}Stämme im {{ config('dpsg.site.region') }} mit Kontakt und den Stufen, die sie anbieten.
            </p>
        @endif

        <form method="get" action="{{ route('gruppierungen.index') }}" class="mt-6 flex flex-wrap items-end gap-4 bg-surface-muted p-4">
            @if ($mehrereBezirke)
                <label class="flex flex-col text-sm font-bold">
                    Bezirk
                    <select name="bezirk" class="mt-1 min-w-48 border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal">
                        <option value="">Alle Bezirke</option>
                        @foreach ($bezirke as $b)
                            <option value="{{ $b->slug }}" @selected($filterBezirk === $b->slug)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="flex flex-col text-sm font-bold">
                Stufe
                <select name="stufe" class="mt-1 min-w-48 border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal">
                    <option value="">Alle Stufen</option>
                    @foreach ($stages as $key => $stage)
                        <option value="{{ $key }}" @selected($filterStufe === $key)>{{ $stage['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="bg-dpsg-blau px-4 py-2 font-bold text-white hover:bg-dpsg-rot">Filtern</button>
            @if ($filterBezirk || $filterStufe)
                <a href="{{ route('gruppierungen.index') }}" class="py-2 text-dpsg-rot underline">Filter zurücksetzen</a>
            @endif
        </form>

        <div x-data="{ view: 'liste' }" x-on:show-map.window="view = 'karte'" class="mt-6">
            {{-- Umschalter nur auf kleinen Bildschirmen und nur mit JS. --}}
            <div class="mb-4 flex gap-2 lg:hidden" x-cloak role="group" aria-label="Ansicht">
                <button type="button" @click="view = 'liste'" :aria-pressed="view === 'liste'" :class="view === 'liste' ? 'bg-dpsg-blau text-white' : 'bg-white text-dpsg-blau'" class="flex-1 border border-dpsg-blau px-4 py-2 font-bold">Liste</button>
                <button type="button" @click="view = 'karte'; $nextTick(() => window.dispatchEvent(new Event('map-visible')))" :aria-pressed="view === 'karte'" :class="view === 'karte' ? 'bg-dpsg-blau text-white' : 'bg-white text-dpsg-blau'" class="flex-1 border border-dpsg-blau px-4 py-2 font-bold">Karte</button>
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                {{-- Ohne JavaScript bleibt die Karte ausgeblendet (x-cloak), die Liste ist vollständig nutzbar. --}}
                <div x-cloak :class="view === 'karte' ? 'block' : 'hidden lg:block'" class="lg:col-span-3">
                    <div class="relative lg:sticky lg:top-20">
                        <div
                            data-gruppierungen-map
                            data-pmtiles="{{ asset(config('dpsg.map.pmtiles')) }}"
                            data-bbox="{{ json_encode(config('dpsg.map.bbox')) }}"
                            data-geojson="{{ route('gruppierungen.geojson', array_filter(['bezirk' => $filterBezirk, 'stufe' => $filterStufe])) }}"
                            role="region"
                            aria-label="Karte der Bezirke und Stämme"
                            class="h-[28rem] w-full bg-surface-muted shadow-md lg:h-[38rem]"
                        ></div>
                        @if ($mitStandort === 0)
                            <div class="pointer-events-none absolute inset-x-4 top-4 bg-white/95 p-4 text-sm shadow-md">
                                <strong>Standorte werden gerade erfasst.</strong>
                                Sobald die Bezirke und Stämme ihre Daten gemeldet haben, erscheinen sie hier auf der Karte.
                            </div>
                        @endif
                        <div class="mt-2 flex gap-4 text-xs text-text-secondary">
                            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full border-2 border-white bg-dpsg-blau shadow"></span> Bezirk</span>
                            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full border-2 border-white bg-dpsg-rot shadow"></span> Stamm</span>
                        </div>
                    </div>
                </div>

                <div :class="view === 'liste' ? 'block' : 'hidden lg:block'" class="space-y-8 lg:col-span-2">
                    @forelse ($sections as $section)
                        <section aria-labelledby="bezirk-{{ $section['bezirk']->slug }}">
                            @include('gruppierungen.card', ['g' => $section['bezirk'], 'headingId' => 'bezirk-'.$section['bezirk']->slug])
                            <ul class="mt-3 space-y-3 border-l-4 border-dpsg-mittelbeige pl-4">
                                @forelse ($section['staemme'] as $stamm)
                                    <li>@include('gruppierungen.card', ['g' => $stamm])</li>
                                @empty
                                    <li class="text-sm text-text-secondary">Die Stämme dieses Bezirks werden noch erfasst.</li>
                                @endforelse
                            </ul>
                        </section>
                    @empty
                        <p class="text-text-secondary">Für diese Auswahl ist noch keine Gruppierung erfasst.</p>
                    @endforelse

                    @if ($ohneBezirk->isNotEmpty())
                        <section aria-labelledby="ohne-bezirk">
                            <h2 id="ohne-bezirk" class="font-serif text-xl font-bold">Weitere Stämme</h2>
                            <ul class="mt-3 space-y-3">
                                @foreach ($ohneBezirk as $stamm)
                                    <li>@include('gruppierungen.card', ['g' => $stamm])</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
