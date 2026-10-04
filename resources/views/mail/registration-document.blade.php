<x-mail::message>
Hallo {{ $registration->first_name }},

zu deiner Anmeldung für **{{ $registration->event->title }}** ({{ $registration->event->dateLabel() }}) liegt ein neues Dokument für dich bereit: **{{ $label }}**.

Du findest es unter „Meine Anmeldungen“. Dort meldest du dich mit dieser E-Mail-Adresse und einem Code an, den wir dir schicken.

<x-mail::button :url="$url">
Zu meinen Anmeldungen
</x-mail::button>

Gut Pfad!<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
