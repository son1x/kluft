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
        <div class="prose max-w-none">
            {!! str($page->body)->sanitizeHtml() !!}
        </div>
    </article>
@endsection
