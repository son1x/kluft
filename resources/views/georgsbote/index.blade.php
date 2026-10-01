@extends('layouts.app')

@section('seo_title', 'Georgsbote – DPSG Diözesanverband Trier')
@section('seo_description', 'Der Georgsbote, die Zeitschrift des DPSG Diözesanverbands Trier: alle Ausgaben als PDF und Newsletter-Anmeldung.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10">
        <h1 class="font-serif text-3xl font-bold">Georgsbote</h1>

        @if ($intro)
            <div class="prose mt-4 max-w-3xl">
                {!! \Illuminate\Support\Str::before($intro->body, '[TODO') !!}
            </div>
        @endif

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <section class="lg:col-span-2" aria-labelledby="archiv-heading">
                @include('partials.section-heading', ['id' => 'archiv-heading', 'title' => 'Archiv'])

                @forelse ($jahrgaenge as $jahr => $ausgaben)
                    <h3 class="mb-4 mt-2 font-serif text-xl font-bold">Jahrgang {{ $jahr }}</h3>
                    <ul class="mb-10 grid grid-cols-2 gap-5 sm:grid-cols-3">
                        @foreach ($ausgaben as $ausgabe)
                            <li>
                                <a href="{{ $ausgabe->downloadUrl() }}" class="group block bg-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg" target="_blank" rel="noopener">
                                    <div class="flex aspect-[3/4] items-center justify-center overflow-hidden bg-dpsg-hellbeige">
                                        @if ($cover = $ausgabe->coverUrl())
                                            <img src="{{ $cover }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                                        @else
                                            <span class="px-4 text-center font-serif text-2xl font-bold text-dpsg-blau">Georgsbote<br />{{ $ausgabe->issue_no }}/{{ $ausgabe->year }}</span>
                                        @endif
                                    </div>
                                    <div class="p-3">
                                        <span class="block text-xs text-text-secondary">{{ $ausgabe->label() }}</span>
                                        <span class="block font-bold group-hover:text-dpsg-rot">{{ $ausgabe->title }}</span>
                                        <span class="mt-1 block text-sm text-dpsg-rot">PDF öffnen</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @empty
                    <p class="text-text-secondary">Die Ausgaben werden gerade ins Archiv übernommen.</p>
                @endforelse
            </section>

            <aside id="newsletter" aria-labelledby="newsletter-heading" class="self-start bg-dpsg-hellbeige p-6">
                <h2 id="newsletter-heading" class="font-serif text-xl font-bold">Newsletter abonnieren</h2>
                <p class="mt-2 text-sm">Neue Ausgaben direkt ins Postfach. Du bekommst zuerst eine E-Mail mit einem Bestätigungslink – erst danach wirst du eingetragen.</p>

                @if (session('newsletter_status') === 'angefragt')
                    <p class="mt-4 border-l-4 border-stufe-pfadi bg-white p-3 text-sm" role="status">Fast geschafft! Bitte bestätige deine Anmeldung über den Link in der E-Mail, die wir dir gerade geschickt haben.</p>
                @elseif (session('newsletter_status') === 'fehler')
                    <p class="mt-4 border-l-4 border-dpsg-rot bg-white p-3 text-sm" role="alert">Das hat leider nicht geklappt. Bitte versuch es später noch einmal.</p>
                @elseif (session('newsletter_status') === 'inaktiv' || ! $newsletterAktiv)
                    <p class="mt-4 border-l-4 border-dpsg-mittelbeige bg-white p-3 text-sm" role="status">Die Online-Anmeldung wird gerade eingerichtet. Bis dahin kannst du dich per E-Mail an <a href="mailto:{{ config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ config('dpsg.contact.email') }}</a> melden.</p>
                @endif

                @if ($newsletterAktiv)
                    <form method="post" action="{{ route('georgsbote.anmelden') }}" class="mt-4 space-y-3">
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
                            <span>Ich möchte den Georgsboten per E-Mail erhalten. Meine Adresse wird dafür an unseren Versanddienstleister Brevo übermittelt; Abmeldung jederzeit über den Link in jeder Ausgabe. Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>.</span>
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
