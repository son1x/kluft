@extends('layouts.app')

@section('seo_title', 'Status – '.config('dpsg.site.name'))

@push('head')
    <meta name="robots" content="noindex" />
@endpush

@php
    $labels = [\App\Support\Health\HealthChecks::OK => 'In Ordnung', \App\Support\Health\HealthChecks::WARN => 'Hinweis', \App\Support\Health\HealthChecks::FAIL => 'Störung', \App\Support\Health\HealthChecks::OFF => 'Nicht eingerichtet'];
    $dot = fn (string $s) => match ($s) { 'ok' => 'bg-[#1e8e3e]', 'warnung' => 'bg-[#f2b600]', 'aus' => 'bg-dpsg-mittelbeige', default => 'bg-[#d93025]' };
@endphp

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="font-serif text-3xl font-bold">Systemstatus</h1>
        <p class="mt-2 text-text-secondary">Betriebszustand der Website und ihrer Dienste. Geprüft {{ $checkedAt->timezone(config('app.timezone'))->format('d.m.Y, H:i') }} Uhr.</p>

        <div @class([
            'mt-6 flex items-center gap-3 p-5 font-bold',
            'bg-[#1e7a36] text-white' => $overall === 'ok',
            'bg-[#f2b600] text-text' => $overall === 'warnung',
            'bg-[#b3261e] text-white' => $overall === 'fehler',
        ]) role="status">
            {{ match ($overall) { 'ok' => 'Alle Systeme laufen.', 'warnung' => 'Alles erreichbar – es gibt Hinweise.', default => 'Es gibt eine Störung.' } }}
        </div>

        <ul class="mt-6 divide-y divide-surface-muted border-y border-surface-muted">
            @foreach ($results as $result)
                <li class="flex items-start gap-4 py-4">
                    <span class="mt-1.5 h-3 w-3 shrink-0 rounded-full {{ $dot($result['status']) }}" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold">{{ $result['label'] }} <span class="ml-1 text-sm font-normal text-text-secondary">– {{ $labels[$result['status']] }}</span></p>
                        <p class="text-sm">{{ $result['message'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

        <p class="mt-6 text-sm text-text-secondary">Maschinenlesbar für Überwachungsdienste: <a href="{{ route('status.json') }}" class="underline">/api/status</a> (bei einer Störung HTTP 503). Probleme bitte an <a href="mailto:{{ config('dpsg.contact.email') }}" class="underline">{{ config('dpsg.contact.email') }}</a>.</p>
    </div>
@endsection
