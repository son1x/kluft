{{-- Nachricht im Nachrichten-Eingang (ContactMessageResource). Erwartet: $nachricht --}}
<dl class="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[9rem_1fr]">
    <dt class="font-medium text-gray-500">Eingang</dt>
    <dd>{{ $nachricht->created_at->format('d.m.Y H:i') }} Uhr</dd>
    <dt class="font-medium text-gray-500">Von</dt>
    <dd>{{ $nachricht->name }} &lt;<a href="mailto:{{ $nachricht->email }}" class="underline">{{ $nachricht->email }}</a>&gt;</dd>
    <dt class="font-medium text-gray-500">Thema</dt>
    <dd>{{ $nachricht->thema }} ({{ \App\Models\ContactMessage::ARTEN[$nachricht->art] ?? $nachricht->art }})</dd>
    <dt class="font-medium text-gray-500">Zugestellt an</dt>
    <dd>{{ implode(', ', $nachricht->empfaenger) }}@unless ($nachricht->zugestellt) – <strong>Mail fehlgeschlagen</strong>@endunless</dd>
    @if ($nachricht->notiz)
        <dt class="font-medium text-gray-500">Notiz</dt>
        <dd>{{ $nachricht->notiz }}</dd>
    @endif
</dl>
<div class="mt-4 whitespace-pre-wrap rounded bg-gray-50 p-3 text-sm dark:bg-gray-900" data-nachricht>{{ $nachricht->nachricht }}</div>
