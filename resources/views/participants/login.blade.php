@extends('layouts.app')

@section('seo_title', 'Meine Anmeldungen – '.config('dpsg.site.name'))

@push('head')
    <meta name="robots" content="noindex" />
@endpush

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12">
        <h1 class="font-serif text-3xl font-bold">Meine Anmeldungen</h1>
        <p class="mt-4">Hier siehst du alle Anmeldungen zu Veranstaltungen des {{ config('dpsg.site.level_genitive') }}, die unter deiner E-Mail-Adresse laufen – auch für Kinder oder Gruppen, die du angemeldet hast. Du kannst stornieren und Rechnungen oder Teilnahmebescheinigungen herunterladen. Ein Konto brauchst du nicht: Wir schicken dir einen Code per E-Mail.</p>

        @if (session('status'))
            <p class="mt-6 border-l-4 border-stufe-pfadi bg-surface-muted p-4" role="status">{{ session('status') }}</p>
        @endif

        @if ($pendingEmail)
            <div class="mt-8 bg-dpsg-hellbeige p-6">
                <p>Wenn es unter <strong>{{ $pendingEmail }}</strong> Anmeldungen gibt, haben wir dir gerade einen sechsstelligen Code geschickt. Er gilt {{ \App\Support\ParticipantLogin::CODE_MINUTES }} Minuten. Schau auch im Spam-Ordner nach.</p>
                <form method="post" action="{{ route('participants.verify') }}" class="mt-4">
                    @csrf
                    <label for="code" class="block text-sm font-bold">Code aus der E-Mail</label>
                    <div class="mt-1 flex flex-wrap gap-3">
                        <input id="code" type="text" name="code" required inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" autofocus
                               @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                               class="w-40 border border-dpsg-mittelbeige bg-white px-3 py-2 font-mono text-xl tracking-widest" />
                        <button type="submit" class="bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">Anmelden</button>
                    </div>
                    @error('code')<p id="code-error" class="mt-2 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                </form>
                <form method="post" action="{{ route('participants.restart') }}" class="mt-4 text-sm">
                    @csrf
                    <button type="submit" class="underline">Andere E-Mail-Adresse verwenden oder neuen Code anfordern</button>
                </form>
            </div>
        @else
            <form method="post" action="{{ route('participants.code') }}" class="mt-8 bg-dpsg-hellbeige p-6">
                @csrf
                <div class="hidden" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
                </div>
                <label for="email" class="block text-sm font-bold">E-Mail-Adresse, mit der du dich angemeldet hast</label>
                <div class="mt-1 flex flex-wrap gap-3">
                    <input id="email" type="email" name="email" required maxlength="255" value="{{ old('email') }}" autocomplete="email"
                           @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                           class="min-w-0 flex-1 border border-dpsg-mittelbeige bg-white px-3 py-2" />
                    <button type="submit" class="bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">Code schicken</button>
                </div>
                @error('email')<p id="email-error" class="mt-2 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
            </form>
        @endif

        <p class="mt-8 text-sm text-text-secondary">Wir zeigen nur Anmeldungen, die noch gespeichert sind. Anmeldedaten löschen wir nach der Frist, die bei der Anmeldung genannt wurde. Mehr dazu in der <a href="{{ url('/datenschutz') }}" class="underline">Datenschutzerklärung</a>.</p>
    </div>
@endsection
