@extends('layouts.app')

@section('seo_title', 'Aktuelles – '.$config['label'].' – DPSG Diözesanverband Trier')

@section('content')
    @include('sections.nav', ['section' => $section, 'current' => 'aktuelles'])
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="mb-8 font-serif text-3xl font-bold">Aktuelles aus der {{ $config['label'] }}</h1>
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
