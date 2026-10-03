{{-- Anmelde-Kasten für Beiträge, die zu einer Veranstaltung einladen.
     Passt Text und Button an den Stand der Anmeldung an.
     Erwartet: $event; optional $compact (schmale Variante am Textende) --}}
@php
    $state = $event->registrationState();
    $open = in_array($state, ['offen', 'warteliste'], true);
    $url = route('events.show', $event->slug).($open ? '#anmeldung' : '');
    $closes = $event->registrationClosesAt()->locale('de');
    $status = match ($state) {
        'offen' => 'Anmeldeschluss: '.$closes->isoFormat('dd, D. MMMM YYYY'),
        'warteliste' => 'Alle Plätze vergeben – Anmeldung auf die Warteliste bis '.$closes->isoFormat('D. MMMM YYYY'),
        'bald' => 'Anmeldung ab '.$event->reg_opens_at?->locale('de')->isoFormat('dd, D. MMMM YYYY, HH:mm').' Uhr',
        'ausgebucht' => 'Leider ausgebucht',
        'abgesagt' => 'Die Veranstaltung ist abgesagt',
        default => 'Die Anmeldung ist geschlossen',
    };
@endphp
@if ($compact ?? false)
    <p class="not-prose mt-8 flex flex-wrap items-center gap-x-4 gap-y-2 border-l-4 border-dpsg-rot bg-surface-muted p-4">
        <span class="font-bold">{{ $event->title }}</span>
        <span class="text-sm text-text-secondary">{{ $status }}</span>
        <a href="{{ $url }}" class="ml-auto inline-flex items-center gap-1 font-bold text-dpsg-rot underline">{{ $open ? 'Zur Anmeldung' : 'Zur Veranstaltung' }} <x-heroicon-o-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
    </p>
@else
    <aside @class(['not-prose my-8 overflow-hidden rounded-lg shadow-md', 'bg-dpsg-blau text-white' => $open, 'bg-surface-muted text-text' => ! $open]) aria-label="Anmeldung: {{ $event->title }}">
        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between md:p-6">
            <div class="flex items-start gap-4">
                <span @class(['flex h-12 w-12 shrink-0 items-center justify-center rounded-full', 'bg-white/15' => $open, 'bg-white' => ! $open]) aria-hidden="true">
                    <x-heroicon-o-ticket class="h-6 w-6" />
                </span>
                <div>
                    <p @class(['text-xs font-bold uppercase tracking-wider', 'text-white/75' => $open, 'text-text-secondary' => ! $open])>{{ $open ? 'Jetzt anmelden' : 'Anmeldung' }}</p>
                    <p class="font-serif text-xl font-bold leading-tight">{{ $event->title }}</p>
                    <p @class(['mt-1 text-sm', 'text-white/85' => $open, 'text-text-secondary' => ! $open])>{{ $event->dateLabel() }}@if ($event->location) · {{ $event->location }}@endif</p>
                    <p class="mt-1 text-sm font-bold">{{ $status }}</p>
                </div>
            </div>
            <a href="{{ $url }}" @class([
                'inline-flex shrink-0 items-center justify-center gap-2 px-5 py-3 font-bold transition',
                'bg-white text-dpsg-blau hover:bg-dpsg-yellow' => $open,
                'border border-dpsg-blau text-dpsg-blau hover:bg-dpsg-blau hover:text-white' => ! $open,
            ])>
                {{ $open ? ($state === 'warteliste' ? 'Auf die Warteliste' : 'Zur Anmeldung') : 'Zur Veranstaltung' }}
                <x-heroicon-o-arrow-right class="h-5 w-5" aria-hidden="true" />
            </a>
        </div>
    </aside>
@endif
