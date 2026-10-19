@extends('layouts.app')

{{-- Formular an eine einzelne Adresse (Funktion „E-Mail-Adressen öffnen ein
     Formular“). Erwartet: $an (signierter Schlüssel), $email, $empfaenger --}}

@section('seo_title', 'Nachricht an '.$empfaenger['name'].' – '.config('dpsg.site.name'))

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10">
        <section class="bg-white p-6 shadow-md md:p-8" aria-labelledby="mailformular-heading">
            <h1 id="mailformular-heading" class="font-serif text-2xl font-bold">Nachricht an {{ $empfaenger['name'] }}</h1>
            @if ($empfaenger['rolle'])
                <p class="mt-1 text-text-secondary">{{ $empfaenger['rolle'] }}</p>
            @endif

            @if (session('kontakt_status') === 'gesendet')
                <div class="mt-6 border-l-4 border-stufe-pfadi bg-surface-muted p-4" role="status">
                    <p class="font-bold">Danke für deine Nachricht!</p>
                    <p class="mt-1 text-sm">Sie ist unterwegs. Die Antwort kommt an die E-Mail-Adresse, die du angegeben hast.</p>
                </div>
            @else
                @if (session('kontakt_status') === 'fehler')
                    <p class="mt-4 border-l-4 border-dpsg-rot bg-surface-muted p-3 text-sm" role="alert">Das hat leider nicht geklappt. Bitte versuch es später noch einmal oder schreib direkt an <a href="mailto:{{ $email }}" class="text-dpsg-rot underline">{{ $email }}</a>.</p>
                @elseif (session('kontakt_status') === 'abgelaufen')
                    <p class="mt-4 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-3 text-sm" role="alert">Das Formular war sehr lange geöffnet. Bitte schick es noch einmal ab – deine Eingaben sind noch da.</p>
                @endif

                <form method="post" action="{{ route('mailformular.senden') }}" class="mt-6 space-y-4" novalidate>
                    <input type="hidden" name="an" value="{{ $an }}" />
                    @include('partials.message-form-fields', ['idPrefix' => 'nachricht', 'withSubject' => true])
                    <button type="submit" class="inline-flex items-center gap-2 bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">
                        <x-heroicon-o-paper-airplane class="h-5 w-5" aria-hidden="true" /> Nachricht senden
                    </button>
                </form>
            @endif

            <p class="mt-6 border-t border-surface-muted pt-4 text-sm text-text-secondary">
                Lieber mit dem eigenen Mailprogramm? <a href="mailto:{{ $email }}" class="text-dpsg-rot underline">{{ $email }}</a>
            </p>
        </section>
    </div>
@endsection
