<x-mail::message>
Hallo {{ $name }},

{{-- Der Text kommt von der Veranstaltungsleitung; als Text, nicht als Markdown, damit nichts Fremdes hineingerät. --}}
{!! nl2br(e($text)) !!}

<x-mail::panel>
**{{ $event->title }}** · {{ $event->dateLabel() }}{{ $event->location ? ' · '.$event->location : '' }}

[Zur Veranstaltung]({{ $url }})
</x-mail::panel>

Fragen? Antworte einfach auf diese Mail{{ $event->contact_email ? ', sie geht an '.($event->contact_name ?: $event->contact_email) : '' }}.
@if ($meine)

Deine Anmeldungen findest du unter [Meine Anmeldungen]({{ route('participants.index') }}).
@endif
</x-mail::message>
