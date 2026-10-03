@extends('layouts.app')

@section('seo_title', ($action === 'bestaetigen' ? 'Anmeldung bestätigen' : 'Anmeldung stornieren').' – DPSG Diözesanverband Trier')

@push('head')
    <meta name="robots" content="noindex" />
@endpush

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12">
        @if (! $registration)
            <h1 class="font-serif text-3xl font-bold">Link nicht mehr gültig</h1>
            <p class="mt-4">Dieser Link ist abgelaufen oder wurde bereits verwendet. Bei Fragen melde dich bei <a href="mailto:{{ config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ config('dpsg.contact.email') }}</a>.</p>
        @else
            @php($event = $registration->event)
            <h1 class="font-serif text-3xl font-bold">{{ $action === 'bestaetigen' ? 'Anmeldung bestätigen' : 'Anmeldung stornieren' }}</h1>
            <div class="mt-6 bg-dpsg-hellbeige p-6">
                <p class="font-serif text-xl font-bold">{{ $event->title }}</p>
                <p class="text-sm">{{ $event->dateLabel() }}</p>
                <p class="mt-3">Anmeldung für <strong>{{ $registration->fullName() }}</strong></p>
            </div>
            {{-- Bestätigen: Der Browser schickt das Formular sofort selbst ab, damit
                 ein Klick in der Mail reicht. Linkscanner in Mailprogrammen rufen
                 nur die Seite ab (ohne JavaScript) und bestätigen so nichts; ohne
                 JavaScript bleibt der Button. Stornieren fragt bewusst nach. --}}
            <form method="post" action="{{ route($action === 'bestaetigen' ? 'registrations.confirm.submit' : 'registrations.cancel.submit', $token) }}" class="mt-6"
                  @if ($action === 'bestaetigen') x-data x-init="$nextTick(() => $el.submit())" @endif>
                @csrf
                @if ($action === 'bestaetigen')
                    <template x-if="true">
                        <p class="mb-4 flex items-center gap-2 font-bold" role="status">
                            <svg class="h-5 w-5 animate-spin text-dpsg-blau" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" /></svg>
                            Einen Moment – deine Anmeldung wird bestätigt …
                        </p>
                    </template>
                    <button type="submit" class="bg-dpsg-blau px-6 py-3 font-bold text-white hover:bg-dpsg-rot">Ja, Anmeldung bestätigen</button>
                @else
                    <p class="mb-4">Willst du die Anmeldung wirklich stornieren? Dein Platz wird dann an die nächste Person auf der Warteliste vergeben.</p>
                    <button type="submit" class="bg-dpsg-rot px-6 py-3 font-bold text-white hover:bg-dpsg-blau">Ja, Anmeldung stornieren</button>
                    <a href="{{ route('events.show', $event->slug) }}" class="ml-4 underline">Nein, zurück</a>
                @endif
            </form>
        @endif
    </div>
@endsection
