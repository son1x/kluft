@extends('layouts.app')

{{-- WhatsApp-Newsletter: Anmeldung per QR-Code/Link mit START (Double-Opt-In
     über die eigene Nachricht), optional per Formular. --}}

@section('seo_title', 'WhatsApp-Newsletter – '.config('dpsg.site.name'))
@section('seo_description', 'Neuigkeiten, Termine und Anmeldestarts des '.config('dpsg.site.name_genitive').' per WhatsApp. Anmelden mit START, abmelden mit STOP.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">WhatsApp-Newsletter</h1>
        <p class="mt-3 max-w-3xl text-lg">Neuigkeiten, Termine und Anmeldestarts aus dem {{ config('dpsg.site.level') }} direkt auf dein Handy – nicht öfter als etwa zweimal im Monat.</p>

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <section aria-labelledby="anmelden" class="lg:col-span-2">
                <h2 id="anmelden" class="font-serif text-2xl font-bold">So meldest du dich an</h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-[auto_1fr] sm:items-start">
                    <img src="{{ asset('images/whatsapp/anmelden-qr.svg') }}" alt="QR-Code: öffnet WhatsApp mit der Nachricht START an {{ $display }}" width="264" height="264" class="h-auto w-56 border border-dpsg-mittelbeige bg-white p-2" />
                    <ol class="list-decimal space-y-2 pl-5">
                        <li>QR-Code mit dem Handy scannen – oder auf dem Handy auf den Button tippen.</li>
                        <li>WhatsApp öffnet sich mit der Nachricht <strong>START</strong> an unsere Büronummer {{ $display }}.</li>
                        <li>Nachricht absenden. Du bekommst sofort eine Willkommensnachricht – fertig.</li>
                    </ol>
                </div>
                <p class="mt-6">
                    <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="inline-block bg-dpsg-blau px-5 py-3 font-bold text-white hover:bg-dpsg-rot">WhatsApp öffnen und START senden</a>
                </p>

                <h2 class="mt-10 font-serif text-xl font-bold">Abmelden</h2>
                <p class="mt-2">Schreib jederzeit <strong>STOP</strong> in den Chat. Deine Nummer wird sofort gelöscht.</p>

                @if ($formEnabled)
                    <section id="formular" aria-labelledby="formular-h" class="mt-10 bg-dpsg-hellbeige p-6">
                        <h2 id="formular-h" class="font-serif text-xl font-bold">Lieber mit deiner Nummer anmelden?</h2>
                        <p class="mt-2 text-sm">Du bekommst eine WhatsApp mit der Bitte, mit START zu antworten. Erst danach bist du eingetragen.</p>
                        @if (session('whatsapp_status') === 'angefragt')
                            <p class="mt-4 border-l-4 border-stufe-pfadi bg-white p-3 text-sm" role="status">Danke! Wenn die Nummer bei WhatsApp ist, bekommst du gleich eine Nachricht. Antworte darauf mit START.</p>
                        @elseif (session('whatsapp_status') === 'abgelaufen')
                            <p class="mt-4 border-l-4 border-dpsg-rot bg-white p-3 text-sm" role="alert">Das Formular war zu lange geöffnet. Bitte noch einmal absenden.</p>
                        @endif
                        <form method="post" action="{{ route('whatsapp.subscribe') }}" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="_t" value="{{ \App\Support\SpamGuard::token() }}" />
                            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label></div>
                            <label class="block text-sm font-bold">
                                Mobilnummer
                                <input type="tel" name="nummer" required value="{{ old('nummer') }}" autocomplete="tel" placeholder="0151 23456789" class="mt-1 w-full max-w-xs border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal" />
                            </label>
                            @error('nummer')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                            <label class="flex gap-2 text-sm"><input type="checkbox" name="alter" value="1" required class="mt-1" @checked(old('alter')) /> <span>Ich bin mindestens 16 Jahre alt.</span></label>
                            @error('alter')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                            <label class="flex gap-2 text-sm"><input type="checkbox" name="einwilligung" value="1" required class="mt-1" @checked(old('einwilligung')) /> <span>Ich möchte den WhatsApp-Newsletter erhalten und habe die Hinweise rechts gelesen.</span></label>
                            @error('einwilligung')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                            <button type="submit" class="bg-dpsg-blau px-4 py-2 font-bold text-white hover:bg-dpsg-rot">Bestätigungsnachricht anfordern</button>
                        </form>
                    </section>
                @endif
            </section>

            <aside aria-labelledby="datenschutz-h" class="self-start bg-dpsg-hellbeige p-6 text-sm">
                <h2 id="datenschutz-h" class="font-serif text-xl font-bold">Gut zu wissen</h2>
                <ul class="mt-3 list-disc space-y-2 pl-5">
                    <li>Der Newsletter ist für Menschen <strong>ab 16 Jahren</strong>.</li>
                    <li>Wir speichern nur deine <strong>Nummer</strong> (verschlüsselt) und den <strong>Zeitpunkt deiner Anmeldung</strong> – keinen Namen, kein Profilbild, keine Nachrichten.</li>
                    <li>Andere Abonnent:innen sehen deine Nummer nicht.</li>
                    <li>Der Versand läuft über die WhatsApp Business Platform von Meta. Dabei fallen bei Meta Metadaten an (wer wann mit welcher Nummer schreibt), die auch in die USA übermittelt werden können.</li>
                    <li>Über diese Nummer beantworten wir keine Nachrichten. Fragen bitte an <a href="mailto:{{ config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ config('dpsg.contact.email') }}</a>.</li>
                </ul>
                <p class="mt-3">Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>.</p>
            </aside>
        </div>
    </div>
@endsection
