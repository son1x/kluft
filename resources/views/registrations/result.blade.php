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
        <p class="mt-8"><a href="{{ route('events.index') }}" class="font-bold text-dpsg-rot underline">Zu den Veranstaltungen</a></p>
    </div>
@endsection
