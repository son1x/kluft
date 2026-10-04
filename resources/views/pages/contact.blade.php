@extends('layouts.app')

{{-- Kontaktseite: Text aus dem CMS (Adresse, Telefon) und daneben das
     Kontaktformular (F-CMS-09). Erwartet: $page, $topics, $selected --}}

@section('seo_title', $page->seo_title ?: $page->title.' – '.config('dpsg.site.name'))
@section('seo_description', $page->seo_description ?: 'Kontakt zum '.config('dpsg.site.name').': '.\App\Support\Site::officeLabel().', Stufen, Fachbereiche – per Formular, E-Mail oder Telefon.')

@section('content')
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-10 lg:grid-cols-5">
        <article class="lg:col-span-2">
            <h1 class="mb-6 font-serif text-3xl font-bold">{{ $page->title }}</h1>
            <div class="prose max-w-none">
                {!! str($page->body)->sanitizeHtml()->glossar()->embeds() !!}
            </div>
        </article>

        <section id="kontaktformular" class="scroll-mt-24 self-start bg-white p-6 shadow-md md:p-8 lg:col-span-3" aria-labelledby="kontaktformular-heading">
            <h2 id="kontaktformular-heading" class="font-serif text-2xl font-bold">Schreib uns</h2>
            <p class="mt-1 text-sm text-text-secondary">Wir antworten dir per E-Mail – meist innerhalb weniger Tage.</p>

            @if (session('kontakt_status') === 'gesendet')
                <div class="mt-6 border-l-4 border-stufe-pfadi bg-surface-muted p-4" role="status">
                    <p class="font-bold">Danke für deine Nachricht!</p>
                    <p class="mt-1 text-sm">Sie ist unterwegs zu den zuständigen Ansprechpersonen. Die Antwort kommt an die E-Mail-Adresse, die du angegeben hast.</p>
                </div>
            @else
                @if (session('kontakt_status') === 'fehler')
                    <p class="mt-4 border-l-4 border-dpsg-rot bg-surface-muted p-3 text-sm" role="alert">Das hat leider nicht geklappt. Bitte versuch es später noch einmal oder schreib direkt an <a href="mailto:{{ config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ config('dpsg.contact.email') }}</a>.</p>
                @elseif (session('kontakt_status') === 'abgelaufen')
                    <p class="mt-4 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-3 text-sm" role="alert">Das Formular war sehr lange geöffnet. Bitte schick es noch einmal ab – deine Eingaben sind noch da.</p>
                @endif

                <form method="post" action="{{ route('contact.send') }}" class="mt-6 space-y-4" novalidate>
                    @if ($topics->isNotEmpty())
                        <div>
                            <label for="kontakt-thema" class="block text-sm font-bold">Worum geht es?</label>
                            <select id="kontakt-thema" name="thema" required
                                    @error('thema') aria-invalid="true" aria-describedby="kontakt-thema-error" @enderror
                                    class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">
                                <option value="" @selected(! $selected)>Bitte wählen …</option>
                                @foreach ($topics as $topic)
                                    <option value="{{ $topic->slug }}" @selected($selected === $topic->slug)>{{ $topic->label }}</option>
                                @endforeach
                            </select>
                            @error('thema')<p id="kontakt-thema-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    @include('partials.message-form-fields', ['idPrefix' => 'kontakt', 'withSubject' => true])
                    <button type="submit" class="inline-flex items-center gap-2 bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">
                        <x-heroicon-o-paper-airplane class="h-5 w-5" aria-hidden="true" /> Nachricht senden
                    </button>
                </form>
            @endif
        </section>
    </div>
@endsection
