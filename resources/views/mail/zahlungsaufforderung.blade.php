<x-mail::message>
Hallo {{ $name }},

bitte überweise **{{ \App\Support\Zahlungen::euro($aufforderung->betragCent) }}** für „{{ $zweck }}“@if ($faellig) bis zum **{{ $faellig }}**@endif.

<x-mail::panel>
Empfänger: {{ $aufforderung->empfaenger }}<br>
IBAN: {{ trim(chunk_split((string) $aufforderung->iban, 4, ' ')) }}<br>
@if ($aufforderung->bic)
BIC: {{ $aufforderung->bic }}<br>
@endif
Betrag: {{ \App\Support\Zahlungen::euro($aufforderung->betragCent) }}<br>
Verwendungszweck: **{{ $aufforderung->verwendungszweck }}**
</x-mail::panel>

Bitte gib den Verwendungszweck genau so an, damit wir die Zahlung zuordnen können.

@if ($aufforderung->qrPngBase64)
Mit der Banking-App geht es schneller: Den GiroCode scannen, alle Angaben sind dann ausgefüllt.

<img src="{{ $message->embedData(base64_decode($aufforderung->qrPngBase64), 'girocode.png', 'image/png') }}" alt="GiroCode für die Überweisung" width="220" height="220">
@endif

@if ($hinweis)
{{ $hinweis }}
@endif

Danke und Gut Pfad
{{ config('dpsg.site.name') }}
</x-mail::message>
