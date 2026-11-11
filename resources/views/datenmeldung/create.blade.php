@extends('layouts.app')

{{-- „Meine Daten aktualisieren“ (NaMi-Datenabgleich). --}}

@section('seo_title', 'Meine Daten aktualisieren – '.config('dpsg.site.name'))

@php($input = 'mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12">
        <h1 class="font-serif text-3xl font-bold">Meine Daten aktualisieren</h1>
        <p class="mt-4">Ist bei uns noch alles aktuell? Trag hier die aktuellen Daten ein – für dich selbst oder für dein Kind. Wir gleichen sie mit der Mitgliederverwaltung der DPSG (NaMi) ab und korrigieren dort, was nicht mehr stimmt. Für Geschwister das Formular bitte einmal je Kind ausfüllen.</p>

        @if ($errors->any())
            <div class="mt-4 border-l-4 border-dpsg-rot bg-white p-4" role="alert">
                <p class="font-bold">Bitte prüfe deine Angaben.</p>
                <ul class="mt-1 list-disc pl-5 text-sm">@foreach ($errors->all() as $fehler)<li>{{ $fehler }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="post" action="{{ route('datenmeldung.store') }}" class="mt-6 space-y-5 bg-white p-6 shadow-md" novalidate>
            @csrf
            <input type="hidden" name="_t" value="{{ \App\Support\SpamGuard::token() }}" />
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label></div>

            <fieldset class="space-y-1">
                <legend class="font-bold">Wer füllt aus?</legend>
                <label class="mr-6 inline-flex items-center gap-2"><input type="radio" name="rolle" value="erziehungsberechtigt" @checked(old('rolle', 'erziehungsberechtigt') === 'erziehungsberechtigt') /> Ich bin erziehungsberechtigt</label>
                <label class="inline-flex items-center gap-2"><input type="radio" name="rolle" value="mitglied" @checked(old('rolle') === 'mitglied') /> Ich bin selbst das Mitglied</label>
            </fieldset>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 font-bold">Mitglied</legend>
                <div><label for="vorname" class="block text-sm font-bold">Vorname *</label><input id="vorname" name="vorname" type="text" required maxlength="100" value="{{ old('vorname') }}" class="{{ $input }}" /></div>
                <div><label for="nachname" class="block text-sm font-bold">Nachname *</label><input id="nachname" name="nachname" type="text" required maxlength="100" value="{{ old('nachname') }}" class="{{ $input }}" /></div>
                <div><label for="geburtsdatum" class="block text-sm font-bold">Geburtsdatum *</label><input id="geburtsdatum" name="geburtsdatum" type="date" required max="{{ now()->toDateString() }}" value="{{ old('geburtsdatum') }}" class="{{ $input }}" /></div>
                <div><label for="geschlecht" class="block text-sm font-bold">Geschlecht</label>
                    <select id="geschlecht" name="geschlecht" class="{{ $input }}"><option value="">keine Angabe</option>@foreach (\App\Support\Zuschuesse::GESCHLECHTER as $k => $l)<option value="{{ $k }}" @selected(old('geschlecht') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="sm:col-span-2"><label for="strasse" class="block text-sm font-bold">Straße und Hausnummer *</label><input id="strasse" name="strasse" type="text" required maxlength="150" autocomplete="street-address" value="{{ old('strasse') }}" class="{{ $input }}" /></div>
                <div><label for="plz" class="block text-sm font-bold">PLZ *</label><input id="plz" name="plz" type="text" inputmode="numeric" required maxlength="10" autocomplete="postal-code" value="{{ old('plz') }}" class="{{ $input }}" /></div>
                <div><label for="ort" class="block text-sm font-bold">Ort *</label><input id="ort" name="ort" type="text" required maxlength="150" autocomplete="address-level2" value="{{ old('ort') }}" class="{{ $input }}" /></div>
                <div><label for="telefon" class="block text-sm font-bold">Telefon</label><input id="telefon" name="telefon" type="tel" maxlength="40" value="{{ old('telefon') }}" class="{{ $input }}" /></div>
                <div><label for="email" class="block text-sm font-bold">E-Mail des Mitglieds</label><input id="email" name="email" type="email" maxlength="254" value="{{ old('email') }}" class="{{ $input }}" /></div>
                <div class="sm:col-span-2"><label for="email_eltern" class="block text-sm font-bold">E-Mail der Erziehungsberechtigten</label><input id="email_eltern" name="email_eltern" type="email" maxlength="254" value="{{ old('email_eltern') }}" class="{{ $input }}" />
                    <p class="mt-1 text-sm text-text-secondary">An die Adresse der Person, die ausfüllt, schicken wir den Bestätigungslink.</p></div>
            </fieldset>

            <label class="flex gap-3 text-sm">
                <input type="checkbox" name="einwilligung" value="1" class="mt-1 h-5 w-5 shrink-0" @checked(old('einwilligung')) />
                <span>Ich willige ein, dass der {{ config('dpsg.site.name') }} diese Angaben mit der Mitgliederverwaltung der DPSG (NaMi) abgleicht und dort korrigiert. Die Angaben hier werden nach spätestens sechs Monaten gelöscht. Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>. *</span>
            </label>
            <button type="submit" class="bg-dpsg-blau px-6 py-3 font-bold text-white hover:bg-dpsg-rot">Absenden</button>
        </form>
    </div>
@endsection
