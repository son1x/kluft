<dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
    <div><dt class="font-semibold">Name</dt><dd>{{ $registration->fullName() }}</dd></div>
    <div><dt class="font-semibold">E-Mail</dt><dd>{{ $registration->email }}</dd></div>
    <div><dt class="font-semibold">Status</dt><dd>{{ $registration->statusLabel() }}</dd></div>
    @foreach ($fields as $key => $field)
        @php($value = $key === 'birth_year' ? $registration->birth_year : ($registration->data[$key] ?? null))
        <div><dt class="font-semibold">{{ $field['label'] }}</dt><dd>{{ $registration->event->fieldValueLabel($field, $value) ?? '–' }}</dd></div>
    @endforeach
    @if ($registration->guardian_name)
        <div><dt class="font-semibold">Angemeldet durch (sorgeberechtigt)</dt><dd>{{ $registration->guardian_name }} – Einwilligung {{ $registration->guardian_consent ? 'erteilt' : 'fehlt' }}</dd></div>
    @endif
    <div><dt class="font-semibold">Einwilligung</dt><dd>{{ $registration->consent_at?->format('d.m.Y H:i') }}, Textversion {{ $registration->consent_text_version }}</dd></div>
    <div><dt class="font-semibold">Wird gelöscht am</dt><dd>{{ $registration->purge_at?->format('d.m.Y') }}</dd></div>
</dl>
