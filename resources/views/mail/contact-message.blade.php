Neue Nachricht über die Website {{ config('app.url') }}

Thema:   {!! $topic !!}
Von:     {!! $senderName !!} <{!! $senderEmail !!}>
@if ($context)
Bezug:   {!! $context !!}
@endif
@if ($subjectLine)
Betreff: {!! $subjectLine !!}
@endif

----------------------------------------------------------------------
{!! $body !!}
----------------------------------------------------------------------

Zum Antworten einfach auf diese E-Mail antworten – die Antwort geht direkt an {!! $senderName !!}.
Die Nachricht wurde nicht auf der Website gespeichert.
