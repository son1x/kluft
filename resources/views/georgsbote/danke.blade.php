@extends('layouts.app')

@section('seo_title', 'Anmeldung bestätigt – Georgsbote')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 text-center">
        <h1 class="font-serif text-3xl font-bold">Danke – du bist dabei!</h1>
        <p class="mt-4">Deine Anmeldung zum Georgsboten ist bestätigt. Die nächste Ausgabe kommt direkt in dein Postfach.</p>
        <a href="{{ route('georgsbote.index') }}" class="mt-8 inline-block border border-dpsg-rot px-4 py-2 text-dpsg-rot hover:bg-dpsg-rot hover:text-white">Zum Georgsbote-Archiv</a>
    </div>
@endsection
