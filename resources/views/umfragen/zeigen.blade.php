@extends('layouts.app')

{{-- Teilnahme an einer Umfrage (docs/umfragen.md). Bedingte Fragen blendet
     Alpine aus (umfrage.js); ohne JavaScript sind alle Fragen sichtbar,
     ausgeblendete Antworten verwirft der Server. Erwartet: $umfrage, $token, $vorschau --}}

@section('seo_title', $umfrage->titel.' – '.config('dpsg.site.name'))
@push('head')<meta name="robots" content="noindex" />@endpush

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10">
        @if ($vorschau)
            <p class="mb-6 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-3 text-sm" role="status">Vorschau – Antworten werden nicht gespeichert.</p>
        @endif
        <h1 class="font-serif text-3xl font-bold">{{ $umfrage->titel }}</h1>
        @if (filled($umfrage->einleitung))
            <div class="prose mt-4 max-w-none">{!! str($umfrage->einleitung)->sanitizeHtml() !!}</div>
        @endif
        <p class="mt-3 text-sm text-text-secondary">
            @if ($umfrage->anonym)
                Die Umfrage ist anonym: Wir speichern weder Namen noch E-Mail- oder IP-Adresse, nur deine Antworten und den Tag.
            @else
                Die Umfrage ist <strong>nicht anonym</strong>: Deine Antworten werden deiner Anmeldung zugeordnet bzw. zusammen mit den Angaben gespeichert, die du hier machst.
            @endif
            Sie werden nach {{ $umfrage->aufbewahrung_monate }} Monaten gelöscht. Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>.
        </p>

        @if (session('umfrage_status') === 'fehler')
            <p class="mt-6 border-l-4 border-dpsg-rot bg-surface-muted p-3 text-sm" role="alert">Bitte prüfe die markierten Fragen.</p>
        @elseif (session('umfrage_status') === 'abgelaufen')
            <p class="mt-6 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-3 text-sm" role="alert">Das Formular war sehr lange geöffnet. Bitte schick es noch einmal ab – deine Eingaben sind noch da.</p>
        @endif

        <form method="post" action="{{ $vorschau ? '#' : route('umfragen.absenden', $umfrage->code) }}" class="mt-8 space-y-6" novalidate x-data="umfrage">
            @csrf
            <input type="hidden" name="_t" value="{{ \App\Support\SpamGuard::token() }}" />
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label></div>
            @if ($token)<input type="hidden" name="t" value="{{ $token }}" />@endif

            @foreach ($umfrage->fragen as $frage)
                @php($k = $frage->schluessel)
                @php($fehler = $errors->first('a.'.$k))
                @php($alt = old('a.'.$k))
                <fieldset data-frage="{{ $k }}" @if ($frage->bedingung && filled($frage->bedingung['frage'] ?? null)) data-bedingung="{{ json_encode($frage->bedingung) }}" x-show="zeigen($el)" @endif
                          class="{{ $frage->typ === 'abschnitt' ? 'pt-4' : 'bg-white p-5 shadow-md' }}" @if ($fehler) aria-invalid="true" aria-describedby="fehler-{{ $k }}" @endif>
                    @if ($frage->typ === 'abschnitt')
                        <legend class="font-serif text-2xl font-bold">{{ $frage->text }}</legend>
                        @if ($frage->hilfe)<p class="mt-2">{!! nl2br(e($frage->hilfe)) !!}</p>@endif
                    @else
                        <legend class="font-bold">{{ $frage->text }}@if ($frage->pflicht) <span class="text-dpsg-rot" aria-hidden="true">*</span><span class="sr-only">(Pflichtfrage)</span>@endif</legend>
                        @if ($frage->hilfe)<p class="mt-1 text-sm text-text-secondary">{!! nl2br(e($frage->hilfe)) !!}</p>@endif

                        <div class="mt-3">
                            @switch($frage->typ)
                                @case('einfach')
                                @case('bewertung')
                                @case('janein')
                                    <div class="{{ $frage->typ === 'janein' ? 'flex gap-6' : 'space-y-2' }}">
                                        @foreach (\App\Support\Umfragen::moeglichkeiten($frage) as $i => $m)
                                            <label class="flex items-center gap-2"><input type="radio" name="a[{{ $k }}]" value="{{ $m }}" @checked($alt === $m) class="h-4 w-4" /> {{ $m }}</label>
                                        @endforeach
                                    </div>
                                    @break
                                @case('mehrfach')
                                    <div class="space-y-2">
                                        @foreach (\App\Support\Umfragen::moeglichkeiten($frage) as $m)
                                            <label class="flex items-center gap-2"><input type="checkbox" name="a[{{ $k }}][]" value="{{ $m }}" @checked(in_array($m, (array) $alt, true)) class="h-4 w-4" /> {{ $m }}</label>
                                        @endforeach
                                    </div>
                                    @break
                                @case('skala')
                                    @php([$von, $bis] = \App\Support\Umfragen::skala($frage))
                                    <div class="flex flex-wrap items-end gap-3">
                                        @if ($frage->einstellung('label_von'))<span class="text-sm text-text-secondary">{{ $frage->einstellung('label_von') }}</span>@endif
                                        @foreach (range($von, $bis) as $n)
                                            <label class="flex flex-col items-center text-sm"><input type="radio" name="a[{{ $k }}]" value="{{ $n }}" @checked((string) $alt === (string) $n) class="h-5 w-5" /> {{ $n }}</label>
                                        @endforeach
                                        @if ($frage->einstellung('label_bis'))<span class="text-sm text-text-secondary">{{ $frage->einstellung('label_bis') }}</span>@endif
                                    </div>
                                    @break
                                @case('kurztext')
                                    <input type="text" name="a[{{ $k }}]" maxlength="500" value="{{ $alt }}" aria-label="{{ $frage->text }}" class="w-full border border-dpsg-mittelbeige bg-white px-3 py-2" />
                                    @break
                                @case('langtext')
                                    <textarea name="a[{{ $k }}]" maxlength="5000" rows="5" aria-label="{{ $frage->text }}" class="w-full border border-dpsg-mittelbeige bg-white px-3 py-2">{{ $alt }}</textarea>
                                    @break
                            @endswitch
                        </div>

                        @if ($frage->einstellung('sonstiges'))
                            <label class="mt-2 block text-sm">Sonstiges:
                                <input type="text" name="sonstiges[{{ $k }}]" maxlength="500" value="{{ old('sonstiges.'.$k) }}" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2" />
                            </label>
                        @endif
                        @if ($frage->kommentar)
                            <label class="mt-3 block text-sm text-text-secondary">Hier hast du Platz für eigene Gedanken (optional)
                                <textarea name="kommentar[{{ $k }}]" maxlength="2000" rows="2" class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">{{ old('kommentar.'.$k) }}</textarea>
                            </label>
                        @endif
                        @if ($fehler)<p id="fehler-{{ $k }}" class="mt-2 text-sm text-dpsg-rot">{{ $fehler }}</p>@endif
                    @endif
                </fieldset>
            @endforeach

            @unless ($vorschau)
                <button type="submit" class="inline-flex items-center gap-2 bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">
                    <x-heroicon-o-paper-airplane class="h-5 w-5" aria-hidden="true" /> Absenden
                </button>
            @endunless
        </form>
    </div>
@endsection
