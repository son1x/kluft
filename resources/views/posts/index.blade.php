@extends('layouts.app')

@section('seo_title', 'Aktuelles – DPSG Diözesanverband Trier')
@section('seo_description', 'Neuigkeiten aus dem DPSG Diözesanverband Trier: Berichte, Einladungen und Termine aus Stufen, Fachbereichen und Gremien.')

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10">
        <div class="mb-8 flex flex-wrap items-baseline justify-between gap-4">
            <h1 class="font-serif text-3xl font-bold">Aktuelles</h1>
            <a href="{{ route('feed') }}" class="inline-flex items-center gap-1 text-sm underline hover:text-dpsg-rot"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3a1 1 0 0 0 0 2 11 11 0 0 1 11 11 1 1 0 1 0 2 0A13 13 0 0 0 4 3Zm0 5a1 1 0 0 0 0 2 6 6 0 0 1 6 6 1 1 0 1 0 2 0 8 8 0 0 0-8-8Zm1 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z" /></svg> RSS-Feed</a>
        </div>

        @forelse ($posts as $post)
            <article class="mb-8 pb-8 border-b border-surface-muted">
                @if ($post->stage)
                    <div class="mb-2">@include('partials.stufe-badge', ['stage' => $post->stage])</div>
                @endif
                <h2 class="font-serif text-xl font-bold mb-1">
                    <a href="{{ url('/aktuelles/'.$post->slug) }}" class="hover:underline">{{ $post->title }}</a>
                </h2>
                <p class="text-sm text-text-secondary mb-2">
                    {{ $post->published_at?->translatedFormat('d. F Y') }}
                    @if ($post->event?->acceptsRegistrations())<span class="ml-2 inline-flex items-center gap-1 rounded-full bg-dpsg-blau px-2 py-0.5 align-middle text-xs font-bold text-white"><x-heroicon-o-ticket class="h-3.5 w-3.5" aria-hidden="true" />Anmeldung offen</span>@endif
                </p>
                <p>{{ $post->teaser }}</p>
            </article>
        @empty
            <p class="text-text-secondary">Noch keine Beiträge veröffentlicht.</p>
        @endforelse

        {{ $posts->links() }}
    </div>
@endsection
