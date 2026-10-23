@extends('layouts.app')

@section('seo_title', 'Meine Anmeldungen – '.config('dpsg.site.name'))

@push('head')
    <meta name="robots" content="noindex" />
@endpush

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-12">
        <div class="flex flex-wrap items-baseline justify-between gap-4">
            <h1 class="font-serif text-3xl font-bold">Meine Anmeldungen</h1>
            <form method="post" action="{{ route('participants.logout') }}">
                @csrf
                <button type="submit" class="text-sm underline">Abmelden</button>
            </form>
        </div>
        <p class="mt-2 text-text-secondary">Angemeldet als {{ $email }}</p>

        @if (session('status'))
            <p class="mt-6 border-l-4 border-stufe-pfadi bg-surface-muted p-4" role="status">{{ session('status') }}</p>
        @endif

        @if ($upcoming->isEmpty() && $past->isEmpty())
            <p class="mt-8">Unter dieser Adresse sind gerade keine Anmeldungen gespeichert. <a href="{{ route('events.index') }}" class="font-bold text-dpsg-rot underline">Zu den Veranstaltungen</a></p>
        @endif

        @foreach (['Bevorstehend' => $upcoming, 'Vergangen' => $past] as $heading => $list)
            @continue($list->isEmpty())
            <h2 class="mt-10 font-serif text-2xl font-bold">{{ $heading }}</h2>
            <ul class="mt-4 space-y-4">
                @foreach ($list as $registration)
                    @php($event = $registration->event)
                    <li class="bg-dpsg-hellbeige p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-serif text-xl font-bold">
                                    @if ($event->status !== 'draft')
                                        <a href="{{ route('events.show', $event->slug) }}" class="hover:underline">{{ $event->title }}</a>
                                    @else
                                        {{ $event->title }}
                                    @endif
                                </p>
                                <p class="text-sm">{{ $event->dateLabel() }}@if ($event->location) · {{ $event->location }}@endif</p>
                                <p class="mt-2">{{ $registration->fullName() }}</p>
                            </div>
                            <span @class([
                                'px-2 py-1 text-xs font-bold uppercase tracking-wide',
                                'bg-stufe-pfadi text-white' => $registration->status === 'confirmed',
                                'bg-stufe-woelflinge text-dpsg-blau' => $registration->status === 'waitlist',
                                'bg-dpsg-mittelbeige' => in_array($registration->status, ['pending', 'cancelled'], true),
                            ])>{{ $registration->statusLabel() }}</span>
                        </div>

                        @if ($event->status === 'cancelled')
                            <p class="mt-3 font-bold text-dpsg-rot">Die Veranstaltung wurde abgesagt.</p>
                        @endif
                        @if ($registration->status === 'pending')
                            <p class="mt-3 text-sm">Noch nicht bestätigt – bitte klicke auf den Link in der E-Mail, die wir dir nach der Anmeldung geschickt haben.</p>
                        @endif

                        @if ($registration->preis_cent && in_array($registration->status, ['confirmed', 'waitlist', 'cancelled'], true))
                            @php($zahlung = $registration->zahlung())
                            <div class="mt-4 border-l-4 border-dpsg-hellbeige bg-surface-muted p-3 text-sm">
                                <p><span class="font-bold">Teilnahmebeitrag:</span> {{ \App\Support\Zahlungen::euro($registration->preis_cent) }}@if ($registration->preis_bezeichnung) ({{ $registration->preis_bezeichnung }})@endif
                                    @if ($zahlung) – <span class="font-bold">{{ \App\Models\Zahlung::STATUS[$zahlung->status] ?? $zahlung->status }}</span>@endif</p>
                                @if ($zahlung && in_array($zahlung->status, ['offen', 'teilweise'], true) && ($konto = \App\Support\Zahlungen::konto()))
                                    <div class="mt-2 flex flex-wrap items-start gap-4">
                                        <dl class="grid grid-cols-[auto_1fr] gap-x-3">
                                            <dt>Empfänger</dt><dd>{{ $konto['inhaber'] }}</dd>
                                            <dt>IBAN</dt><dd class="font-mono">{{ trim(chunk_split($konto['iban'], 4, ' ')) }}</dd>
                                            <dt>Betrag</dt><dd>{{ \App\Support\Zahlungen::euro($zahlung->offenCent()) }}</dd>
                                            <dt>Verwendungszweck</dt><dd class="font-mono font-bold">{{ $zahlung->referenz }}</dd>
                                            @if ($zahlung->faellig_am)<dt>Bis</dt><dd>{{ $zahlung->faellig_am->format('d.m.Y') }}</dd>@endif
                                        </dl>
                                        <img src="data:image/png;base64,{{ \App\Support\Zahlungen::weg($zahlung)->anfordern($zahlung)->qrPngBase64 }}" alt="GiroCode für die Überweisung" width="140" height="140" class="bg-white" />
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($registration->certificateApproved() && ! $registration->uploadedCertificate())
                            <p class="mt-4">
                                <a href="{{ route('participants.certificate', $registration) }}" class="inline-flex items-center gap-2 font-bold text-dpsg-blau underline hover:text-dpsg-rot">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a1 1 0 0 1 1 1v7.59l2.3-2.3a1 1 0 1 1 1.4 1.42l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.42L9 11.6V4a1 1 0 0 1 1-1ZM4 15a1 1 0 0 1 1 1h10a1 1 0 1 1 0 2H5a2 2 0 0 1-2-2 1 1 0 0 1 1-1Z" /></svg>
                                    Teilnahmebescheinigung (PDF)
                                </a>
                            </p>
                        @endif

                        @if ($registration->documents->isNotEmpty())
                            <ul class="mt-4 space-y-1">
                                @foreach ($registration->documents as $document)
                                    <li>
                                        <a href="{{ route('participants.document', $document) }}" class="inline-flex items-center gap-2 font-bold text-dpsg-blau underline hover:text-dpsg-rot">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a1 1 0 0 1 1 1v7.59l2.3-2.3a1 1 0 1 1 1.4 1.42l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.42L9 11.6V4a1 1 0 0 1 1-1ZM4 15a1 1 0 0 1 1 1h10a1 1 0 1 1 0 2H5a2 2 0 0 1-2-2 1 1 0 0 1 1-1Z" /></svg>
                                            {{ $document->label() }} (PDF)
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if (in_array($registration->status, ['confirmed', 'waitlist'], true) && $event->starts_at->isFuture())
                            <details class="mt-4">
                                <summary class="cursor-pointer text-sm underline">Anmeldung stornieren …</summary>
                                <form method="post" action="{{ route('participants.cancel', $registration) }}" class="mt-3">
                                    @csrf
                                    <p class="text-sm">Willst du die Anmeldung von {{ $registration->fullName() }} wirklich stornieren? Ein frei werdender Platz geht an die Warteliste.</p>
                                    <button type="submit" class="mt-2 bg-dpsg-rot px-4 py-2 text-sm font-bold text-white hover:bg-dpsg-blau">Ja, stornieren</button>
                                </form>
                            </details>
                        @endif

                        @if ($registration->purge_at && $registration->status !== 'pending')
                            <p class="mt-4 text-xs text-text-secondary">Wird am {{ $registration->purge_at->format('d.m.Y') }} gelöscht – speichere Dokumente, die du behalten möchtest.</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </div>
@endsection
