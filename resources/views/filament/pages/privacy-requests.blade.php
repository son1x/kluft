<x-filament-panels::page>
    <x-filament::section heading="Person suchen" description="Auskunft, Berichtigung oder Löschung nach KDG: Alles, was das CMS zu einer E-Mail-Adresse speichert. Vor einer Auskunft die Identität prüfen (Anfrage von genau dieser Adresse oder schriftlich).">
        <form wire:submit="search" style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-start;">
            <div style="flex: 1 1 18rem;">
                <x-filament::input.wrapper :valid="! $errors->has('email')">
                    <x-filament::input type="email" wire:model="email" placeholder="name@example.org" autocomplete="off" />
                </x-filament::input.wrapper>
                @error('email')<p class="text-danger-600" style="margin-top: .25rem; font-size: .875rem;">{{ $message }}</p>@enderror
            </div>
            <x-filament::button type="submit">Suchen</x-filament::button>
        </form>
    </x-filament::section>

    @if ($found)
        <x-filament::section heading="Veranstaltungsanmeldungen ({{ $found['registrations']->count() }})">
            @forelse ($found['registrations'] as $registration)
                <p style="font-size: .875rem; margin-bottom: .5rem;">
                    <a href="{{ $eventUrl($registration->event) }}" class="text-primary-600" style="text-decoration: underline;">{{ $registration->event->title }}</a>
                    ({{ $registration->event->dateLabel() }}) – {{ $registration->fullName() }}, {{ $registration->statusLabel() }}
                    @if ($registration->documents->isNotEmpty()) · {{ $registration->documents->count() }} Dokument(e) @endif
                    · Löschung geplant am {{ $registration->purge_at?->format('d.m.Y') ?? '–' }}
                </p>
            @empty
                <p style="font-size: .875rem;">Keine.</p>
            @endforelse
            <p style="font-size: .75rem; margin-top: .5rem;" class="text-gray-500">Berichtigung: in der Veranstaltung unter „Teilnehmende“.</p>
        </x-filament::section>

        <x-filament::section heading="Personenvorstellung ({{ $found['people']->count() }})">
            @forelse ($found['people'] as $person)
                <p style="font-size: .875rem;"><a href="{{ $personUrl($person) }}" class="text-primary-600" style="text-decoration: underline;">{{ $person->name }}</a> – {{ $person->role }}</p>
            @empty
                <p style="font-size: .875rem;">Keine.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Redaktionskonto ({{ $found['users']->count() }})">
            @forelse ($found['users'] as $user)
                <p style="font-size: .875rem;"><a href="{{ $userUrl($user) }}" class="text-primary-600" style="text-decoration: underline;">{{ $user->name }}</a> – Status {{ $user->status }}, letzte Anmeldung {{ $user->last_login_at?->format('d.m.Y') ?? 'nie' }}. Wird beim Löschen nicht automatisch entfernt.</p>
            @empty
                <p style="font-size: .875rem;">Keins.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Weitere Stellen">
            <ul style="font-size: .875rem; list-style: disc; padding-left: 1.25rem;">
                <li>Mails in der Warteschlange an diese Adresse: {{ $found['queued'] }}</li>
                @if (\App\Support\Features::newsletter())
                    <li>{{ config('dpsg.newsletter.name') }} (Newsletter): Adressen liegen nur bei Brevo – dort unter „Kontakte“ suchen, Auskunft/Löschung dort.</li>
                @endif
                <li>Kontaktformular: Nachrichten werden nicht im CMS gespeichert, nur per Mail zugestellt (Postfächer prüfen).</li>
                <li>Sicherungen: Gelöschte Daten verschwinden aus den Backups, wenn diese rotiert sind (14 Tage).</li>
            </ul>
        </x-filament::section>
    @endif

    @if (\App\Support\WhatsAppMode::api())
    <x-filament::section heading="WhatsApp-Newsletter: Nummer suchen" description="Gespeichert sind nur Nummer (verschlüsselt), Status, Weg der Anmeldung und Einwilligungsnachweis.">
        <form wire:submit="searchPhone" style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-start;">
            <div style="flex: 1 1 18rem;">
                <x-filament::input.wrapper :valid="! $errors->has('phone')">
                    <x-filament::input type="tel" wire:model="phone" placeholder="0151 23456789" autocomplete="off" />
                </x-filament::input.wrapper>
                @error('phone')<p class="text-danger-600" style="margin-top: .25rem; font-size: .875rem;">{{ $message }}</p>@enderror
            </div>
            <x-filament::button type="submit" color="gray">Nummer suchen</x-filament::button>
        </form>

        @if ($whatsapp)
            <div style="margin-top: 1rem; font-size: .875rem;">
                @if ($whatsapp['subscriber'])
                    @php($s = $whatsapp['subscriber'])
                    <p><strong>{{ $whatsapp['phone'] }}</strong>: {{ $s->status === 'active' ? 'aktiv' : 'unbestätigt' }}, angemeldet über {{ $s->source === 'link' ? 'QR-Code/Link' : 'Formular' }},
                        Einwilligung (Version {{ $s->consent_version }}) bestätigt am {{ $s->confirmed_at?->format('d.m.Y H:i') ?? '–' }}{{ $s->requested_at ? ', angefragt am '.$s->requested_at->format('d.m.Y H:i') : '' }}.</p>
                @else
                    <p><strong>{{ $whatsapp['phone'] }}</strong>: kein Abonnement.</p>
                @endif
                @if ($whatsapp['suppressed'])
                    <p>Auf der Sperrliste (nach Abmeldung, nur als nicht umkehrbarer Prüfwert).</p>
                @endif
                @if ($whatsapp['subscriber'] || $whatsapp['suppressed'])
                    <div style="margin-top: .75rem;">
                        <x-filament::button color="danger" size="sm" wire:click="deleteWhatsApp" wire:confirm="WhatsApp-Daten zu dieser Nummer endgültig löschen?">WhatsApp-Daten löschen</x-filament::button>
                    </div>
                @endif
            </div>
        @endif
    </x-filament::section>
    @endif
</x-filament-panels::page>
