@extends('layouts.app')

@section('seo_title', 'Aktuelles – DPSG Diözesanverband Trier')

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10">
        <h1 class="font-serif text-3xl font-bold mb-8">Aktuelles</h1>

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
