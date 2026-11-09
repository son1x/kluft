<x-mail::message>
@switch($art)
@case('confirmed')
**{{ $person }}**@if ($stamm) ({{ $stamm }})@endif hat sich zu **{{ $event->title }}** ({{ $event->dateLabel() }}) angemeldet und die Anmeldung bestätigt.
@break
@case('waitlist')
**{{ $person }}**@if ($stamm) ({{ $stamm }})@endif hat sich zu **{{ $event->title }}** ({{ $event->dateLabel() }}) angemeldet und steht auf der Warteliste.
@break
@case('promoted')
**{{ $person }}**@if ($stamm) ({{ $stamm }})@endif ist bei **{{ $event->title }}** ({{ $event->dateLabel() }}) von der Warteliste nachgerückt.
@break
@default
**{{ $person }}**@if ($stamm) ({{ $stamm }})@endif hat die Anmeldung zu **{{ $event->title }}** ({{ $event->dateLabel() }}) storniert.
@endswitch

Stand: {{ $stand }}.

<x-mail::button :url="$url">
Anmeldungen ansehen
</x-mail::button>

Alle Angaben stehen nur im Backend, nicht in dieser Mail. Diese Info kommt, weil bei der Veranstaltung „Sammeladresse informieren“ oder „Verantwortliche Person informieren“ eingeschaltet ist.
</x-mail::message>
