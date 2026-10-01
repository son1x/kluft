@extends('layouts.app')

@section('seo_title', $post->title.' – DPSG Diözesanverband Trier')
@section('seo_description', $post->teaser)

@section('content')
    <article class="max-w-3xl mx-auto px-4 py-10">
        @if ($post->stage)
            <div class="mb-2">@include('partials.stufe-badge', ['stage' => $post->stage])</div>
        @endif
        <h1 class="font-serif text-3xl font-bold mb-2">{{ $post->title }}</h1>
        <p class="text-sm text-text-secondary mb-6">
            {{ $post->published_at?->translatedFormat('d. F Y') }}
            @if ($post->author) · {{ $post->author->name }} @endif
        </p>
        <div class="prose max-w-none">
            {!! $post->body !!}
        </div>

        <p class="mt-8">
            <a href="{{ url('/aktuelles') }}" class="text-dpsg-blau hover:underline">&larr; Zurück zu Aktuelles</a>
        </p>
    </article>
@endsection
