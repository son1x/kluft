{{-- Gemeinsame Felder für Kontaktformular und Frage zur Veranstaltung:
     Name, E-Mail, Nachricht plus Spamschutz (Honeypot + Zeitfalle).
     Optional: $idPrefix, $withSubject (Feld „Betreff“), $bag (Fehler-Bag) --}}
@php($p = $idPrefix ?? 'kontakt')
@php($bag = $bag ?? 'default')
@csrf
<input type="hidden" name="_t" value="{{ \App\Support\SpamGuard::token() }}" />
<div class="hidden" aria-hidden="true">
    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="{{ $p }}-name" class="block text-sm font-bold">Dein Name</label>
        <input id="{{ $p }}-name" type="text" name="name" required maxlength="100" value="{{ old('name') }}" autocomplete="name"
               @error('name', $bag) aria-invalid="true" aria-describedby="{{ $p }}-name-error" @enderror
               class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2" />
        @error('name', $bag)<p id="{{ $p }}-name-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="{{ $p }}-email" class="block text-sm font-bold">Deine E-Mail-Adresse</label>
        <input id="{{ $p }}-email" type="email" name="email" required maxlength="254" value="{{ old('email') }}" autocomplete="email"
               @error('email', $bag) aria-invalid="true" aria-describedby="{{ $p }}-email-error" @enderror
               class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2" />
        @error('email', $bag)<p id="{{ $p }}-email-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
    </div>
</div>
@if ($withSubject ?? false)
    <div>
        <label for="{{ $p }}-betreff" class="block text-sm font-bold">Betreff <span class="font-normal text-text-secondary">(optional)</span></label>
        <input id="{{ $p }}-betreff" type="text" name="betreff" maxlength="150" value="{{ old('betreff') }}"
               @error('betreff', $bag) aria-invalid="true" aria-describedby="{{ $p }}-betreff-error" @enderror
               class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2" />
        @error('betreff', $bag)<p id="{{ $p }}-betreff-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
    </div>
@endif
<div>
    <label for="{{ $p }}-nachricht" class="block text-sm font-bold">Nachricht</label>
    <textarea id="{{ $p }}-nachricht" name="nachricht" required minlength="10" maxlength="5000" rows="6"
              @error('nachricht', $bag) aria-invalid="true" aria-describedby="{{ $p }}-nachricht-error" @enderror
              class="mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2">{{ old('nachricht') }}</textarea>
    @error('nachricht', $bag)<p id="{{ $p }}-nachricht-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
</div>
<p class="text-xs text-text-secondary">
    @if (\App\Support\Features::enabled(\App\Support\Features::NACHRICHTEN))
        Deine Angaben gehen per E-Mail an die zuständigen Ansprechpersonen, damit sie dir antworten können. Zur Bearbeitung speichern wir die Nachricht höchstens {{ \App\Models\ContactMessage::aufbewahrungMonate() }} Monate; sehen können sie nur die Ansprechpersonen und unsere Verwaltung.
    @else
        Deine Angaben werden nur per E-Mail an die zuständigen Ansprechpersonen weitergeleitet, damit sie dir antworten können, und nicht auf der Website gespeichert.
    @endif
    Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>.
</p>
