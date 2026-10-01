@extends('layouts.app')

@section('seo_title', $page->seo_title ?: $page->title.' – DPSG Diözesanverband Trier')
@if ($page->seo_description)
    @section('seo_description', $page->seo_description)
@endif

@section('content')
    <article class="max-w-3xl mx-auto px-4 py-10">
        @if ($page->stage)
            <div class="mb-2">@include('partials.stufe-badge', ['stage' => $page->stage])</div>
        @endif
        <h1 class="font-serif text-3xl font-bold mb-6">{{ $page->title }}</h1>
        <div class="prose max-w-none">
            {!! $page->body !!}
        </div>
    </article>
@endsection
