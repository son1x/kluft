<x-mail::message>
Hallo {{ $name }},

@switch($kind)
@case('doi')
danke für deine Anmeldung zu **{{ $event->title }}** ({{ $event->dateLabel() }}).

Bitte bestätige sie innerhalb von 48 Stunden über diesen Button. Erst dann ist die Anmeldung gültig – ohne Bestätigung löschen wir die Angaben automatisch.

<x-mail::button :url="$confirmUrl">
Anmeldung bestätigen
</x-mail::button>

Wenn du dich nicht angemeldet hast, kannst du diese E-Mail einfach ignorieren.
@break

@case('confirmed')
deine Anmeldung zu **{{ $event->title }}** ({{ $event->dateLabel() }}) ist bestätigt. Wir freuen uns auf dich!
@break

@case('waitlist')
die Veranstaltung **{{ $event->title }}** ({{ $event->dateLabel() }}) ist im Moment ausgebucht. Du stehst auf der Warteliste – sobald ein Platz frei wird, rückst du automatisch nach und bekommst eine E-Mail.
@break

@case('promoted')
gute Nachrichten: Für **{{ $event->title }}** ({{ $event->dateLabel() }}) ist ein Platz frei geworden. Du bist von der Warteliste nachgerückt und jetzt fest angemeldet.
@break

@case('cancelled')
deine Anmeldung zu **{{ $event->title }}** ist storniert.
@break
@endswitch

@if ($cancelUrl)
Falls du doch nicht teilnehmen kannst, storniere bitte über diesen Link, damit jemand anderes nachrücken kann:
[Anmeldung stornieren]({{ $cancelUrl }})
@endif

@if ($kind !== 'doi')
Alle deine Anmeldungen, Rechnungen und Bescheinigungen findest du unter [Meine Anmeldungen]({{ route('participants.index') }}).

@endif
@if ($event->contact_email)
Fragen beantwortet {{ $event->contact_name ?: 'das Team' }} unter {{ $event->contact_email }}.
@endif

Gut Pfad!<br>
{{ config('dpsg.site.name') }}
</x-mail::message>
