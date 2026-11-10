@extends('layouts.app')

@section('seo_title', $event->title.' – Veranstaltungen – '.config('dpsg.site.name'))
@section('seo_description', (string) ($event->teaser ?: $event->title.' – '.$event->dateLabel()))

@section('content')
    <article class="mx-auto max-w-6xl px-4 py-10">
        @php($schlicht = (bool) request()->attributes->get('testsystem_extern'))
        @unless ($schlicht)
            <nav aria-label="Brotkrumen" class="mb-4 text-sm text-text-secondary">
                <a href="{{ route('events.index') }}" class="hover:underline">Veranstaltungen</a> › <span>{{ $event->title }}</span>
            </nav>
        @endunless

        <div class="grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="flex flex-wrap gap-2 text-xs font-bold">
                    @if ($event->stage)
                        @include('partials.stufe-badge', ['stage' => $event->stage])
                    @else
                        <span class="bg-dpsg-hellbeige px-2 py-1">{{ $event->scopeLabel() }}</span>
                    @endif
                    @if ($event->target_group)
                        <span class="bg-surface-muted px-2 py-1">{{ $event->target_group }}</span>
                    @endif
                </div>
                <h1 class="mt-3 font-serif text-3xl font-bold">{{ $event->title }}</h1>

                @if ($state === 'abgesagt')
                    <p class="mt-4 border-l-4 border-dpsg-rot bg-white p-4 font-bold" role="status">Diese Veranstaltung ist abgesagt.</p>
                @endif

                @if ($event->coverMedia)
                    @include('partials.cover-figure', ['media' => $event->coverMedia])
                @endif

                @if ($event->teaser)
                    <p class="mt-6 text-lg">{{ $event->teaser }}</p>
                @endif
                @if (filled($event->description))
                    {{-- Für Screenreader: Zwischenüberschriften der Beschreibung (oft h3) folgen so nicht direkt auf h1. --}}
                    <h2 class="sr-only">Beschreibung</h2>
                @endif
                <div class="prose mt-4 max-w-none">{!! str($event->description ?? '')->sanitizeHtml()->glossar()->embeds()->mailformular() !!}</div>
            </div>

            <aside class="self-start bg-dpsg-hellbeige p-6" aria-labelledby="eckdaten">
                <h2 id="eckdaten" class="font-serif text-xl font-bold">Auf einen Blick</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-bold">Wann</dt><dd>{{ $event->dateLabel() }}</dd></div>
                    @if ($event->location)<div><dt class="font-bold">Wo</dt><dd>{{ $event->location }}</dd></div>@endif
                    @if ($event->cost_info)<div><dt class="font-bold">Kosten</dt><dd>{{ $event->cost_info }}</dd></div>@endif
                    @if ($event->hatTeilnahmebeitrag())
                        <div><dt class="font-bold">Teilnahmebeitrag</dt><dd>@foreach ($event->preise() as $preis){{ $preis['bezeichnung'] }}: {{ \App\Support\Zahlungen::euro($preis['cent']) }}@unless ($loop->last)<br />@endunless @endforeach</dd></div>
                    @endif
                    @if ($event->memberGruppierung)<div><dt class="font-bold">Für</dt><dd>{{ $event->zielgruppeMitglieder() }}</dd></div>@endif
                    @if ($event->capacity)<div><dt class="font-bold">Plätze</dt><dd>{{ $event->capacity }} @if ($state === 'offen')(noch {{ $event->spotsLeft() }} frei)@endif</dd></div>@endif
                    <div><dt class="font-bold">Anmeldung</dt><dd>
                        @if ($event->reg_opens_at?->isFuture()) ab {{ $event->reg_opens_at->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr, @endif
                        bis {{ $event->registrationClosesAt()->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr
                    </dd></div>
                    @if ($event->contact_name || $event->contact_email)
                        <div><dt class="font-bold">Kontakt</dt><dd>{{ $event->contact_name }}@if ($event->contact_email)<br /><a href="{{ (! $schlicht && \App\Support\Mailformular::aktiv()) ? '#frage' : 'mailto:'.$event->contact_email }}" class="text-dpsg-rot underline">{{ $event->contact_email }}</a>@endif</dd></div>
                    @endif
                </dl>
            </aside>
        </div>

        @php($fotos = $event->fotos()->where('status', 'freigegeben')->with('media')->orderBy('sort')->get()->filter(fn ($f) => $f->media))
        @if ($fotos->isNotEmpty())
            {{-- Freigegebene Fotos (Vier-Augen), als Galerie wie in Beiträgen. --}}
            <section class="mt-12" aria-labelledby="fotos-heading">
                <h2 id="fotos-heading" class="font-serif text-2xl font-bold">Fotos</h2>
                <ul class="galerie not-prose mt-4">
                    @foreach ($fotos as $foto)
                        <li><a href="{{ $foto->media->url() }}" title="Bild in voller Größe öffnen"><img src="{{ $foto->media->url() }}" @if ($foto->media->webpSrcset()) srcset="{{ $foto->media->webpSrcset() }}" sizes="(min-width: 768px) 25vw, 50vw" @endif alt="{{ $foto->media->alt_text }}" loading="lazy" /></a></li>
                    @endforeach
                </ul>
                @if ($credits = $fotos->pluck('media.credit')->filter()->unique()->implode(', '))
                    <p class="mt-2 text-xs text-text-secondary">Fotos: {{ $credits }}</p>
                @endif
            </section>
        @endif

        @include('events.partials.anmeldung')

        {{-- Im schlichten Rahmen (vorab freigegeben) ohne Formular: es läuft über eine nicht freigegebene Adresse. --}}
        @unless ($schlicht)
        {{-- Frage zur Veranstaltung (F-CMS-09): geht per Mail an die Kontaktperson --}}
        <section id="frage" aria-labelledby="frage-heading" class="mt-12 max-w-3xl scroll-mt-24">
            <h2 id="frage-heading" class="font-serif text-2xl font-bold">Fragen zur Veranstaltung?</h2>
            @if (session('frage_status') === 'gesendet')
                <div class="mt-4 border-l-4 border-stufe-pfadi bg-white p-4 shadow" role="status">
                    <p class="font-bold">Danke für deine Frage!</p>
                    <p class="mt-1">Sie ist unterwegs zu {{ $event->contact_name ?: 'den Verantwortlichen' }}. Die Antwort kommt per E-Mail.</p>
                </div>
            @else
                <details class="mt-4 bg-white p-5 shadow-md" @if ($errors->frage->any() || session('frage_status')) open @endif>
                    <summary class="cursor-pointer font-bold text-dpsg-rot">Frage an {{ $event->contact_name ?: 'die Verantwortlichen' }} schreiben</summary>
                    @if (session('frage_status') === 'fehler')
                        <p class="mt-4 border-l-4 border-dpsg-rot bg-surface-muted p-3 text-sm" role="alert">Das hat leider nicht geklappt. Bitte versuch es später noch einmal oder schreib direkt an <a href="mailto:{{ $event->contact_email ?: config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ $event->contact_email ?: config('dpsg.contact.email') }}</a>.</p>
                    @elseif (session('frage_status') === 'abgelaufen')
                        <p class="mt-4 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-3 text-sm" role="alert">Das Formular war sehr lange geöffnet. Bitte schick es noch einmal ab.</p>
                    @endif
                    <form method="post" action="{{ route('events.ask', $event->slug) }}" class="mt-4 space-y-4" novalidate>
                        @include('partials.message-form-fields', ['idPrefix' => 'frage', 'bag' => 'frage'])
                        <button type="submit" class="inline-flex items-center gap-2 bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">
                            <x-heroicon-o-paper-airplane class="h-5 w-5" aria-hidden="true" /> Frage senden
                        </button>
                    </form>
                </details>
            @endif
        </section>
        @endunless
    </article>
@endsection
