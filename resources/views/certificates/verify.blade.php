@extends('layouts.app')

@section('seo_title', 'Teilnahmebescheinigung prüfen – '.config('dpsg.site.name'))
@section('seo_description', 'Echtheit einer Teilnahmebescheinigung des '.config('dpsg.site.name_genitive').' prüfen.')

{{-- Echtheitsprüfung. Erwartet: $code, $name, $result (null|echt|tippfehler|unbekannt), $event --}}
@php($input = 'mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal focus:outline-2 focus:outline-dpsg-blau')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">Teilnahmebescheinigung prüfen</h1>
        <p class="mt-3 text-text-secondary">Gib den Prüfcode von der Bescheinigung und den Namen ein, so wie er dort steht. Wir speichern dabei nichts.</p>

        @if ($result === 'echt')
            <div class="mt-6 border-l-4 border-[#1e8e3e] bg-white p-5 shadow-md" role="status">
                <p class="font-bold text-[#1e8e3e]">Die Bescheinigung ist echt.</p>
                <p class="mt-2">{{ $name }} hat an der Veranstaltung <strong>{{ $event->title }}</strong> teilgenommen ({{ $event->dateLabel() }}@if ($event->location), {{ $event->location }}@endif). Ausgestellt vom {{ config('dpsg.site.name') }}.</p>
            </div>
        @elseif ($result === 'tippfehler')
            <div class="mt-6 border-l-4 border-[#f2b600] bg-white p-5 shadow-md" role="alert">
                <p class="font-bold">Der Prüfcode enthält einen Tippfehler.</p>
                <p class="mt-1 text-sm">Bitte vergleiche ihn noch einmal Zeichen für Zeichen mit der Bescheinigung.</p>
            </div>
        @elseif ($result === 'unbekannt')
            <div class="mt-6 border-l-4 border-[#d93025] bg-white p-5 shadow-md" role="alert">
                <p class="font-bold text-[#d93025]">Diese Kombination aus Prüfcode und Name kennen wir nicht.</p>
                <p class="mt-1 text-sm">Steht der Name genau so auf der Bescheinigung? Bei Fragen hilft {{ \App\Support\Site::office('nom') }}: <a href="mailto:{{ config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ config('dpsg.contact.email') }}</a>.</p>
            </div>
        @endif

        <form method="post" action="{{ route('certificates.check') }}" class="mt-8 space-y-5 bg-white p-6 shadow-md">
            @csrf
            <div>
                <label for="code" class="block text-sm font-bold">Prüfcode</label>
                <input id="code" name="code" type="text" required maxlength="40" autocomplete="off" spellcheck="false" placeholder="TB-…" value="{{ old('code', $code) }}" class="{{ $input }} font-mono uppercase" />
                @error('code')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="name" class="block text-sm font-bold">Name auf der Bescheinigung</label>
                <input id="name" name="name" type="text" required maxlength="200" autocomplete="off" value="{{ old('name', $name) }}" class="{{ $input }}" />
                @error('name')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="bg-dpsg-blau px-5 py-2.5 font-bold text-white hover:bg-dpsg-rot">Prüfen</button>
        </form>
    </div>
@endsection
