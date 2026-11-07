{{-- Nachricht im Nachrichten-Eingang (ContactMessageResource). Erwartet: $nachricht --}}
<dl class="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[9rem_1fr]">
    <dt class="font-medium text-gray-500">Eingang</dt>
    <dd>{{ $nachricht->created_at->format('d.m.Y H:i') }} Uhr</dd>
    <dt class="font-medium text-gray-500">Von</dt>
    <dd>{{ $nachricht->name }} &lt;<a href="mailto:{{ $nachricht->email }}" class="underline">{{ $nachricht->email }}</a>&gt;</dd>
    <dt class="font-medium text-gray-500">Thema</dt>
    <dd>{{ $nachricht->thema }} ({{ \App\Models\ContactMessage::ARTEN[$nachricht->art] ?? $nachricht->art }})</dd>
    <dt class="font-medium text-gray-500">Zugestellt an</dt>
    <dd>{{ implode(', ', $nachricht->empfaenger) }}@if ($nachricht->letztesErgebnis() === 'log') – <strong>nur ins Server-Protokoll geschrieben, nicht verschickt</strong>@elseif (! $nachricht->zugestellt) – <strong>Mail fehlgeschlagen</strong>@endif</dd>
    @if (count($nachricht->versand ?? []) > 1 || $nachricht->letztesErgebnis() === 'log')
        <dt class="font-medium text-gray-500">Versand</dt>
        <dd>
            <ul>
                @foreach ($nachricht->versand as $eintrag)
                    <li>
                        {{ \Illuminate\Support\Carbon::parse($eintrag['am'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }}:
                        {{ \App\Models\ContactMessage::VERSAND_ARTEN[$eintrag['art']] ?? $eintrag['art'] }}
                        an {{ implode(', ', $eintrag['an']) }}
                        – {{ \App\Models\ContactMessage::ERGEBNISSE[$eintrag['ergebnis']] ?? $eintrag['ergebnis'] }}@if ($eintrag['von'] ?? null) ({{ $eintrag['von'] }})@endif
                    </li>
                @endforeach
            </ul>
        </dd>
    @endif
    @if ($nachricht->notiz)
        <dt class="font-medium text-gray-500">Notiz</dt>
        <dd>{{ $nachricht->notiz }}</dd>
    @endif
</dl>
<div class="mt-4 whitespace-pre-wrap rounded bg-gray-50 p-3 text-sm dark:bg-gray-900" data-nachricht>{{ $nachricht->nachricht }}</div>
