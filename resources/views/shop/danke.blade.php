@extends('layouts.app')

@section('seo_title', 'Bestellung abgeschickt – '.config('dpsg.site.name'))

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16">
        <h1 class="font-serif text-3xl font-bold">Fast geschafft</h1>
        <p class="mt-4 text-lg">Wir haben dir eine Mail geschickt. Bitte bestätige deine Bestellung über den Link darin – erst dann gilt sie. Danach bekommst du die Zahlungsaufforderung.</p>
        <p class="mt-6"><a href="{{ route('shop.index') }}" class="font-bold text-dpsg-rot underline">Zurück zur Sammelbestellung</a></p>
    </div>
@endsection
