<x-mail::message>
Hallo,

@if ($moderation)
du moderierst die Videokonferenz **{{ $k->titel }}**.
@else
du bist zur Videokonferenz **{{ $k->titel }}** eingeladen.
@endif

**Wann:** {{ $wann }}

<x-mail::button :url="$teilnahme">
Zur Videokonferenz
</x-mail::button>

Link zum Beitritt: {{ $teilnahme }}

@if ($moderation)
@if ($moderatorLink)
**Dein Moderator-Link** (öffnet den Raum ohne Anmeldung, mit Moderationsrechten, gültig bis {{ $gueltigBis }}):

{{ $moderatorLink }}

Diesen Link bitte **nicht weitergeben**: Wer ihn hat, ist Moderator*in. Den Teilnehmenden genügt der Link oben.
@else
Der Moderator-Link ist nicht mehr gültig. Mitglieder mit Microsoft-Konto können sich direkt anmelden und moderieren.
@endif
@else
Die Konferenz beginnt, sobald die Moderation da ist. Bis dahin bitte kurz warten.
@endif

Im Anhang steht der Termin für deinen Kalender.

Gut Pfad<br>
{{ $absender ?: config('dpsg.site.name') }}
</x-mail::message>
