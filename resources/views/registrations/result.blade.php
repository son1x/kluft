@extends('layouts.app')

@php
    $texts = [
        'confirmed' => ['Anmeldung bestätigt', 'Super, du bist dabei! Eine Bestätigung mit allen Infos und einem Stornolink ist per E-Mail unterwegs.'],
        'waitlist' => ['Du stehst auf der Warteliste', 'Die Veranstaltung ist im Moment ausgebucht. Wird ein Platz frei, rückst du automatisch nach und bekommst eine E-Mail.'],
        'full' => ['Leider ausgebucht', 'In der Zwischenzeit wurden alle Plätze vergeben. Deine Angaben haben wir gelöscht.'],
        'expired' => ['Link abgelaufen', 'Die Anmeldung wurde nicht innerhalb von 48 Stunden bestätigt und ist verfallen. Du kannst dich gern erneut anmelden.'],
        'cancelled' => ['Anmeldung storniert', 'Deine Anmeldung ist storniert. Danke, dass du Bescheid gegeben hast!'],
        'invalid' => ['Link nicht mehr gültig', 'Dieser Link ist abgelaufen oder wurde bereits verwendet.'],
    ];
    [$title, $text] = $texts[$result] ?? $texts['invalid'];
@endphp

@section('seo_title', $title.' – '.config('dpsg.site.name'))

@push('head')
    <meta name="robots" content="noindex" />
@endpush

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12" role="status">
        <h1 class="font-serif text-3xl font-bold">{{ $title }}</h1>
        @if ($registration)
            <p class="mt-2 text-text-secondary">{{ $registration->event->title }} · {{ $registration->event->dateLabel() }}</p>
        @endif
        <p class="mt-4">{{ $text }}</p>
        @if ($registration && ($mit = $registration->mitAngemeldete())->isNotEmpty())
            {{-- Familienanmeldung: Ergebnis je Person. --}}
            @php($statusText = ['confirmed' => 'bestätigt', 'waitlist' => 'Warteliste', 'pending' => 'unbestätigt', 'cancelled' => 'storniert'])
            <ul class="mt-4 space-y-1 border-l-4 border-dpsg-hellbeige pl-4">
                @foreach ($registration->newCollection([$registration])->concat($mit) as $person)
                    <li><strong>{{ $person->first_name }} {{ $person->last_name }}</strong>: {{ $statusText[$person->status] ?? $person->status }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-sm text-text-secondary">Jede Person bekommt eine eigene E-Mail mit Stornolink.</p>
        @endif
        @if (request()->attributes->get('testsystem_extern'))
            {{-- Vorab freigegeben (Testsystem): zurück zur Website statt zur nicht öffentlichen Übersicht. --}}
            @if ($zurueck = \App\Support\Testsystem::zurueckUrl())
                <p class="mt-8"><a href="{{ $zurueck }}" class="font-bold text-dpsg-rot underline">Zur Website</a></p>
            @endif
        @else
            <p class="mt-8"><a href="{{ route('events.index') }}" class="font-bold text-dpsg-rot underline">Zu den Veranstaltungen</a></p>
        @endif
    </div>
@endsection
