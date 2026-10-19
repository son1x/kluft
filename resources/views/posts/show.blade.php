@extends('layouts.app')

@section('seo_title', $post->title.' – '.config('dpsg.site.name'))
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
        @if ($post->coverMedia)
            @include('partials.cover-figure', ['media' => $post->coverMedia, 'class' => 'mb-6'])
        @endif
        @if ($post->event && $post->event->status !== 'draft')
            @include('partials.event-cta', ['event' => $post->event])
        @endif
        <div class="prose max-w-none">
            {!! str($post->body)->sanitizeHtml()->glossar()->embeds()->mailformular()->galerie() !!}
        </div>
        @if ($post->event && $post->event->status !== 'draft')
            @include('partials.event-cta', ['event' => $post->event, 'compact' => true])
        @endif

        @if ($notice = $post->aiNotice())
            <p class="mt-8 flex items-start gap-2 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-3 text-sm" role="note">
                <span class="font-bold">KI-Hinweis:</span> <span>{{ $notice }}</span>
            </p>
        @endif

        <p class="mt-8">
            <a href="{{ url('/aktuelles') }}" class="text-dpsg-blau hover:underline">&larr; Zurück zu Aktuelles</a>
        </p>
    </article>
@endsection
