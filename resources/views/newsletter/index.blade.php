@extends('layouts.app')

{{-- Newsletter der Gliederung (beim DV „Georgsbrief“), Anmeldung per
     Double-Opt-In über Brevo (F-GB-10..16). --}}

@php($newsletter = config('dpsg.newsletter'))
@section('seo_title', $newsletter['title'])
@section('seo_description', $newsletter['description'])

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="sr-only">{{ $newsletter['name'] }}</h1>
        <div class="overflow-hidden rounded-lg bg-[#eff2f7] p-6 shadow-md md:p-10">
            @include('newsletter.logo', ['class' => 'mx-auto h-auto w-full max-w-2xl'])
        </div>

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($intro)
                    <div class="prose prose-lg max-w-none">
                        {!! str($intro->body)->before('[TODO')->sanitizeHtml() !!}
                    </div>
                @else
                    <p class="text-lg">{{ $newsletter['text'] }}</p>
                @endif
                @if (\App\Support\Features::enabled('georgsbote'))
                    <p class="mt-6 text-sm text-text-secondary">
                        Die Zeitschrift des {{ config('dpsg.site.level_genitive') }} mit ausführlichen Berichten gibt es als PDF: <a href="{{ route('georgsbote.index') }}" class="text-dpsg-rot underline">zum Georgsbote-Archiv</a>.
                    </p>
                @endif
            </div>

            <aside id="newsletter" aria-labelledby="newsletter-heading" class="self-start bg-dpsg-hellbeige p-6">
                <h2 id="newsletter-heading" class="font-serif text-xl font-bold">{{ $newsletter['name'] }} abonnieren</h2>
                <p class="mt-2 text-sm">Du bekommst zuerst eine E-Mail mit einem Bestätigungslink – erst danach wirst du eingetragen.</p>

                @if (session('newsletter_status') === 'angefragt')
                    <p class="mt-4 border-l-4 border-stufe-pfadi bg-white p-3 text-sm" role="status">Fast geschafft! Bitte bestätige deine Anmeldung über den Link in der E-Mail, die wir dir gerade geschickt haben.</p>
                @elseif (session('newsletter_status') === 'fehler')
                    <p class="mt-4 border-l-4 border-dpsg-rot bg-white p-3 text-sm" role="alert">Das hat leider nicht geklappt. Bitte versuch es später noch einmal.</p>
                @elseif (session('newsletter_status') === 'inaktiv' || ! $newsletterAktiv)
                    <p class="mt-4 border-l-4 border-dpsg-mittelbeige bg-white p-3 text-sm" role="status">Die Online-Anmeldung wird gerade eingerichtet. Bis dahin kannst du dich per E-Mail an <a href="mailto:{{ config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ config('dpsg.contact.email') }}</a> melden.</p>
                @endif

                @if ($newsletterAktiv)
                    <form method="post" action="{{ route('newsletter.anmelden') }}" class="mt-4 space-y-3">
                        @csrf
                        <div class="hidden" aria-hidden="true">
                            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
                        </div>
                        <label class="block text-sm font-bold">
                            E-Mail-Adresse
                            <input type="email" name="email" required value="{{ old('email') }}" autocomplete="email" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal" />
                        </label>
                        @error('email')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                        <label class="block text-sm font-bold">
                            Vorname <span class="font-normal text-text-secondary">(optional)</span>
                            <input type="text" name="vorname" value="{{ old('vorname') }}" autocomplete="given-name" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal" />
                        </label>
                        <label class="flex gap-2 text-sm">
                            <input type="checkbox" name="einwilligung" value="1" required class="mt-1" @checked(old('einwilligung')) />
                            <span>Ich möchte den {{ $newsletter['name'] }} per E-Mail erhalten. Meine Adresse wird dafür an unseren Versanddienstleister Brevo übermittelt; Abmeldung jederzeit über den Link in jeder Ausgabe. Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>.</span>
                        </label>
                        @error('einwilligung')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                        <button type="submit" class="bg-dpsg-blau px-4 py-2 font-bold text-white hover:bg-dpsg-rot">Anmelden</button>
                    </form>
                @endif

                <h3 class="mt-6 font-bold">Abmelden</h3>
                <p class="text-sm">Jeder Newsletter enthält einen Abmeldelink. Alternativ genügt eine kurze E-Mail an <a href="mailto:{{ config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ config('dpsg.contact.email') }}</a>.</p>
            </aside>
        </div>
    </div>
@endsection
