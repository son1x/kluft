@extends('layouts.app')

@section('seo_title', 'Meine Daten aktualisieren – '.config('dpsg.site.name'))

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12">
        <h1 class="font-serif text-3xl font-bold">{{ $bestaetigt ? 'Danke, bestätigt!' : 'Fast geschafft' }}</h1>
        <p class="mt-4">{{ $bestaetigt ? 'Wir haben die Daten übernommen und gleichen sie mit der Mitgliederverwaltung ab.' : 'Wir haben dir eine E-Mail mit einem Bestätigungslink geschickt. Erst nach dem Klick darauf übernehmen wir die Daten.' }}</p>
        <p class="mt-6"><a href="{{ route('datenmeldung.create') }}" class="text-dpsg-rot underline">Daten für ein weiteres Kind eintragen</a></p>
    </div>
@endsection
