{{-- Mein Konto → Meine Rollen und Rechte (nur lesen). --}}
<x-filament-panels::page>
    @php($user = $this->user())
    @php($rollen = $this->rollen())

    <x-filament::section heading="Mein Konto">
        <dl style="display: grid; grid-template-columns: max-content 1fr; gap: .25rem 1rem; font-size: .875rem;">
            <dt style="font-weight: 600;">Name</dt><dd>{{ $user->name }}</dd>
            <dt style="font-weight: 600;">E-Mail</dt><dd>{{ $user->email }}</dd>
            @if ($user->last_login_at)
                <dt style="font-weight: 600;">Zuletzt angemeldet</dt><dd>{{ $user->last_login_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }} Uhr</dd>
            @endif
        </dl>
    </x-filament::section>

    <x-filament::section heading="Meine Rollen" description="Was du im Backend siehst und bearbeiten darfst, ergibt sich aus diesen Rollen.">
        @if ($rollen === [])
            <p style="font-size: .875rem;">Dir ist keine Rolle zugewiesen.</p>
        @else
            <ul style="display: grid; gap: .75rem; font-size: .875rem;">
                @foreach ($rollen as $rolle)
                    <li>
                        <strong>{{ $rolle['rolle'] }}</strong>
                        @if ($rolle['erklaerung'])
                            <br>{{ ucfirst($rolle['erklaerung']) }}.
                        @endif
                        @if ($rolle['seit'] || $rolle['von'])
                            <br><span style="opacity: .7;">Seit {{ $rolle['seit'] ?? '–' }}@if ($rolle['von']), vergeben von {{ $rolle['von'] }}@endif</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament::section heading="Etwas fehlt?">
        <p style="font-size: .875rem;">
            Rollen vergibt die Verwaltung. Autor:innen für eine Stufe oder einen Bereich können auch die Stufen- und Sektionsadmins freischalten. Wenn du etwas nicht siehst, das du für deine Aufgabe brauchst, sprich sie an und nenne die Rolle, die dir fehlt.
        </p>
    </x-filament::section>
</x-filament-panels::page>
