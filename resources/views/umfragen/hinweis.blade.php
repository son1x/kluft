@extends('layouts.app')

{{-- Hinweis statt Umfrage. Erwartet: $umfrage, $art --}}

@section('seo_title', $umfrage->titel.' – '.config('dpsg.site.name'))
@push('head')<meta name="robots" content="noindex" />@endpush

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">{{ $umfrage->titel }}</h1>
        <div class="mt-6 bg-white p-6 shadow-md">
            @switch($art)
                @case('code')
                    <form method="post" action="{{ route('umfragen.code', $umfrage->code) }}" class="space-y-4" novalidate>
                        @csrf
                        <label for="zugangscode" class="block font-bold">Zugangscode</label>
                        <p class="text-sm text-text-secondary">Diese Umfrage ist mit einem Code geschützt. Du hast ihn zusammen mit dem Link bekommen.</p>
                        <input id="zugangscode" type="text" name="zugangscode" required maxlength="50" autocomplete="off"
                               @error('zugangscode') aria-invalid="true" aria-describedby="zugangscode-error" @enderror
                               class="w-full border border-dpsg-mittelbeige bg-white px-3 py-2" />
                        @error('zugangscode')<p id="zugangscode-error" class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                        <button type="submit" class="bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">Weiter</button>
                    </form>
                    @break
                @case('nur_eingeladene')
                    <p>Diese Umfrage richtet sich an die Teilnehmenden einer Veranstaltung. Bitte nutze den persönlichen Link aus deiner Einladungs-Mail.</p>
                    @break
                @case('noch_nicht')
                    <p>Die Umfrage startet am {{ $umfrage->beginnt_am?->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm') }} Uhr.</p>
                    @break
                @case('schon_teilgenommen')
                    <p class="font-bold">Danke, du hast schon teilgenommen!</p>
                    @break
                @default
                    <p>Diese Umfrage ist beendet. Danke an alle, die mitgemacht haben!</p>
            @endswitch
        </div>
    </div>
@endsection
