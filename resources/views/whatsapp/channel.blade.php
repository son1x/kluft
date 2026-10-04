@extends('layouts.app')

{{-- Kostenlose Variante: WhatsApp-Kanal des Diözesanverbands. Der Verband
     sieht keine Nummern; abonnieren und abbestellen direkt in WhatsApp. --}}

@section('seo_title', 'WhatsApp-Kanal – '.config('dpsg.site.name'))
@section('seo_description', 'Neuigkeiten, Termine und Anmeldestarts des '.config('dpsg.site.name_genitive').' im WhatsApp-Kanal. Kostenlos abonnieren, jederzeit abbestellen.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">WhatsApp-Kanal</h1>
        <p class="mt-3 max-w-3xl text-lg">Neuigkeiten, Termine und Anmeldestarts aus dem {{ config('dpsg.site.level') }} direkt in WhatsApp – nicht öfter als ein paar Mal im Monat.</p>

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <section aria-labelledby="folgen" class="lg:col-span-2">
                <h2 id="folgen" class="font-serif text-2xl font-bold">So folgst du dem Kanal</h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-[auto_1fr] sm:items-start">
                    @if ($qr)
                        <img src="{{ asset('images/whatsapp/kanal-qr.svg') }}" alt="QR-Code: öffnet den WhatsApp-Kanal des {{ config('dpsg.site.name_genitive') }}" width="264" height="264" class="h-auto w-56 border border-dpsg-mittelbeige bg-white p-2" />
                    @endif
                    <ol class="list-decimal space-y-2 pl-5">
                        <li>{{ $qr ? 'QR-Code mit dem Handy scannen – oder' : 'Auf dem Handy' }} auf den Button tippen.</li>
                        <li>WhatsApp öffnet den Kanal. Auf <strong>„Abonnieren“</strong> tippen.</li>
                        <li>Tipp: Über die Glocke im Kanal kannst du Benachrichtigungen einschalten. Sonst siehst du neue Beiträge im Reiter „Aktuelles“.</li>
                    </ol>
                </div>
                <p class="mt-6">
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-block bg-dpsg-blau px-5 py-3 font-bold text-white hover:bg-dpsg-rot">Kanal in WhatsApp öffnen</a>
                </p>

                <h2 class="mt-10 font-serif text-xl font-bold">Abbestellen</h2>
                <p class="mt-2">Im Kanal oben auf den Namen tippen und <strong>„Nicht mehr folgen“</strong> wählen.</p>
            </section>

            <aside aria-labelledby="datenschutz-h" class="self-start bg-dpsg-hellbeige p-6 text-sm">
                <h2 id="datenschutz-h" class="font-serif text-xl font-bold">Gut zu wissen</h2>
                <ul class="mt-3 list-disc space-y-2 pl-5">
                    <li>Wir sehen <strong>nicht</strong>, wer dem Kanal folgt – keine Nummern, keine Namen. Wir sehen nur die Anzahl und Reaktionen.</li>
                    <li>Andere, die dem Kanal folgen, sehen deine Nummer auch nicht.</li>
                    <li>Den Kanal betreibt WhatsApp (Meta). Dabei fallen bei Meta Daten an, die auch in die USA übermittelt werden können; es gelten die Bedingungen von WhatsApp.</li>
                    <li>Nachrichten an uns gehen über den Kanal nicht. Fragen bitte an <a href="mailto:{{ config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ config('dpsg.contact.email') }}</a>.</li>
                </ul>
                <p class="mt-3">Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>.</p>
            </aside>
        </div>
    </div>
@endsection
