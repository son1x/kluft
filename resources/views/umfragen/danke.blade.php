@extends('layouts.app')

{{-- Nach dem Absenden; optional das Gewinnspiel (getrennt von den Antworten).
     Erwartet: $umfrage, $gewinnspiel --}}

@section('seo_title', $umfrage->titel.' – '.config('dpsg.site.name'))
@push('head')<meta name="robots" content="noindex" />@endpush

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">{{ $umfrage->titel }}</h1>
        <div class="mt-6 border-l-4 border-stufe-pfadi bg-white p-6 shadow-md" role="status">
            @if (filled($umfrage->danke))
                <div class="prose max-w-none">{!! str($umfrage->danke)->sanitizeHtml() !!}</div>
            @else
                <p class="font-bold">Danke für deine Teilnahme!</p>
            @endif
        </div>

        @if (session('gewinnspiel_status') === 'eingetragen')
            <p class="mt-6 bg-surface-muted p-4">Du bist im Gewinnspiel eingetragen. Viel Glück!</p>
        @elseif ($gewinnspiel)
            <section class="mt-8 bg-white p-6 shadow-md" aria-labelledby="gewinnspiel-heading">
                <h2 id="gewinnspiel-heading" class="font-serif text-2xl font-bold">Gewinnspiel</h2>
                @if (filled($umfrage->gewinnspiel_text))<p class="mt-2">{!! nl2br(e($umfrage->gewinnspiel_text)) !!}</p>@endif
                <form method="post" action="{{ route('umfragen.gewinnspiel', $umfrage->code) }}" class="mt-4 space-y-4" novalidate>
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="gewinn-name" class="block text-sm font-bold">Dein Name</label>
                            <input id="gewinn-name" type="text" name="name" required maxlength="200" value="{{ old('name') }}" autocomplete="name" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2" />
                            @error('name')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="gewinn-email" class="block text-sm font-bold">Deine E-Mail-Adresse</label>
                            <input id="gewinn-email" type="email" name="email" required maxlength="254" value="{{ old('email') }}" autocomplete="email" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2" />
                            @error('email')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="einwilligung" value="1" class="mt-1 h-4 w-4" />
                        <span>Ich nehme am Gewinnspiel teil. Name und E-Mail-Adresse werden getrennt von meinen Antworten gespeichert, nur zur Benachrichtigung genutzt und nach der Auslosung gelöscht.</span>
                    </label>
                    @error('einwilligung')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                    <button type="submit" class="bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">Teilnehmen</button>
                </form>
            </section>
        @endif
    </div>
@endsection
