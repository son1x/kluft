@extends('layouts.app')

@section('seo_title', $page->seo_title ?: $page->title.' – DPSG Diözesanverband Trier')
@if ($page->seo_description)
    @section('seo_description', $page->seo_description)
@endif

@section('content')
    @if ($page->section && config("dpsg.sections.{$page->section}"))
        @include('sections.nav', ['section' => $page->section, 'current' => $page->slug])
    @endif
    <article class="max-w-3xl mx-auto px-4 py-10">
        @if ($page->parent)
            <nav aria-label="Brotkrumen" class="mb-4 text-sm text-text-secondary">
                <a href="{{ url('/'.$page->parent->slug) }}" class="text-dpsg-rot hover:underline">{{ $page->parent->title }}</a>
                <span aria-hidden="true"> › </span>
                <span aria-current="page">{{ $page->title }}</span>
            </nav>
        @endif
        @if ($page->stage)
            <div class="mb-2">@include('partials.stufe-badge', ['stage' => $page->stage])</div>
        @endif
        <h1 class="font-serif text-3xl font-bold mb-6">{{ $page->title }}</h1>
        @if ($page->slug === 'rechtstraeger')
            @include('partials.rechtstraeger-struktur')
        @endif
        <div class="prose max-w-none">
            {!! str($page->body)->sanitizeHtml()->glossar()->embeds() !!}
        </div>
    </article>

    {{-- Übersichtsseiten (z. B. Diözesanverband): Unterpunkte als Kacheln --}}
    @php($childPages = $page->navChildren)
    @if ($childPages->isNotEmpty())
        <div class="mx-auto mb-12 grid max-w-6xl gap-6 px-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($childPages as $child)
                <a href="{{ url('/'.$child->slug) }}" class="group flex flex-col border-t-4 border-dpsg-blau bg-white p-6 shadow-md transition hover:-translate-y-0.5 hover:border-dpsg-rot hover:shadow-lg">
                    <h2 class="font-serif text-xl font-bold group-hover:text-dpsg-rot">{{ $child->title }}</h2>
                    @if ($teaser = $child->seo_description ?: \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags(\Illuminate\Support\Str::before((string) $child->body, '[TODO')))), 140))
                        <p class="mt-2 flex-1 text-sm text-text-secondary">{{ $teaser }}</p>
                    @endif
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-dpsg-rot">Mehr <x-heroicon-o-arrow-right class="h-4 w-4 transition group-hover:translate-x-1" aria-hidden="true" /></span>
                </a>
            @endforeach
        </div>
    @endif

    @if (($teamPhoto ?? null) || ($people ?? collect())->isNotEmpty())
        @include('partials.team', ['people' => $people, 'teamPhoto' => $teamPhoto])
    @endif
@endsection
