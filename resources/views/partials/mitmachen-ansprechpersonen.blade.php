{{-- „Melde dich gern bei …“: Referent*innen mit Mail-Link, Mitglieder des
     Arbeitskreises (ohne veröffentlichte Adresse) mit Link auf ihre Karte
     auf derselben Seite, dann die Geschäftsstelle.
     Erwartet: $mail (Personen mit publicEmail), $members (Personen im Arbeitskreis) --}}
@php($people = $mail->concat($members))
Melde dich gern
@if ($people->isNotEmpty())
    bei
    @foreach ($people as $person)
        @if ($person->publicEmail())<a href="mailto:{{ $person->publicEmail() }}" class="text-dpsg-rot underline">{{ $person->name }}</a>@else<a href="#{{ $person->anchor() }}" class="text-dpsg-rot underline">{{ $person->name }}</a>@endif{{ $loop->last ? '' : ',' }}
    @endforeach
    oder
@endif
{{ \App\Support\Site::officePrefix('in') }} <a href="{{ \App\Support\Site::officeUrl() }}" class="text-dpsg-rot underline">{{ \App\Support\Site::officeLabel() }}</a>