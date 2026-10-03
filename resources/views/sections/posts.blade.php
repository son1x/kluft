@extends('layouts.app')

@section('seo_title', 'Aktuelles – '.$config['label'].' – DPSG Diözesanverband Trier')

@push('head')
    <link rel="alternate" type="application/rss+xml" title="{{ $config['label'] }} – Aktuelles" href="{{ route('sections.feed', $section) }}" />
@endpush

@section('content')
    @include('sections.nav', ['section' => $section, 'current' => 'aktuelles'])
    <div class="mx-auto max-w-3xl px-4 py-10">
        <div class="mb-8 flex flex-wrap items-baseline justify-between gap-4">
            <h1 class="font-serif text-3xl font-bold">Aktuelles aus der {{ $config['label'] }}</h1>
            <a href="{{ route('sections.feed', $section) }}" class="inline-flex items-center gap-1 text-sm underline hover:text-dpsg-rot"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3a1 1 0 0 0 0 2 11 11 0 0 1 11 11 1 1 0 1 0 2 0A13 13 0 0 0 4 3Zm0 5a1 1 0 0 0 0 2 6 6 0 0 1 6 6 1 1 0 1 0 2 0 8 8 0 0 0-8-8Zm1 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z" /></svg> RSS-Feed</a>
        </div>
        @forelse ($posts as $post)
            <article class="mb-8 border-b border-surface-muted pb-8">
                <h2 class="mb-1 font-serif text-xl font-bold"><a href="{{ url('/aktuelles/'.$post->slug) }}" class="hover:underline">{{ $post->title }}</a></h2>
                <p class="mb-2 text-sm text-text-secondary">{{ $post->published_at?->locale('de')->isoFormat('D. MMMM YYYY') }}</p>
                <p>{{ $post->teaser }}</p>
            </article>
        @empty
            <p class="text-text-secondary">Noch keine Meldungen.</p>
        @endforelse
        {{ $posts->links() }}
    </div>
@endsection
