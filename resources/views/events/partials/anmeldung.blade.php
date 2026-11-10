{{-- Anmeldeformular einer Veranstaltung; auch in der Einbett-Ansicht
     (events/einbetten, $einbetten = true: ohne „Meine Anmeldungen“, Links
     öffnen ein neues Fenster). --}}
@php
    $input = 'mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal focus:outline-2 focus:outline-dpsg-blau';
    $guardianAge = config('dpsg.guardian_consent_age');
    $einbetten ??= false;
    // Ohne Verweise in den Rest der Website: eingebettet oder im schlichten Rahmen (vorab freigegeben).
    $ohneLinks = $einbetten || (bool) request()->attributes->get('testsystem_extern');
@endphp
<section id="anmeldung" aria-labelledby="anmeldung-heading" @class(['max-w-3xl scroll-mt-24', 'mt-12' => ! $einbetten])>
    <h2 id="anmeldung-heading" class="font-serif text-2xl font-bold">Anmeldung</h2>
    @if ($event->memberGruppierung)
        <p class="mt-2 border-l-4 border-dpsg-blau bg-white p-3 text-sm shadow">Diese Veranstaltung ist für {{ $event->zielgruppeMitglieder() }}. Wenn du nicht dazugehörst, melde dich bitte vorher bei der Veranstaltungsleitung.</p>
    @endif
    @if ($state !== 'ohne' && ! $ohneLinks)
        <p class="mt-1 text-sm text-text-secondary">Schon angemeldet? Unter <a href="{{ route('participants.index') }}" class="underline">Meine Anmeldungen</a> kannst du stornieren und Unterlagen abrufen.</p>
    @endif

    @if (session('anmeldung') === 'angefragt')
        <div class="mt-4 border-l-4 border-stufe-pfadi bg-white p-4 shadow" role="status">
            <p class="font-bold">Fast geschafft!</p>
            <p class="mt-1">Wir haben dir eine E-Mail geschickt. Bitte bestätige die Anmeldung innerhalb von 48 Stunden über den Link darin – erst dann ist sie gültig. Schau auch im Spam-Ordner nach.</p>
        </div>
    @elseif ($state === 'abgesagt')
        <p class="mt-4 text-text-secondary">Die Veranstaltung ist abgesagt, eine Anmeldung ist nicht möglich.</p>
    @elseif ($state === 'ohne')
        <p class="mt-4 text-text-secondary">
            Eine Online-Anmeldung gibt es hier nicht.
            @if ($event->contact_email)
                Melde dich bitte direkt bei <a href="{{ (! $ohneLinks && \App\Support\Mailformular::aktiv()) ? '#frage' : 'mailto:'.$event->contact_email }}" class="text-dpsg-rot underline">{{ $event->contact_name ?: $event->contact_email }}</a> an.
            @else
                Melde dich bitte über das <a href="{{ url('/'.config('dpsg.contact_page')) }}" class="text-dpsg-rot underline">Kontaktformular</a> an.
            @endif
        </p>
    @elseif ($state === 'bald')
        <p class="mt-4 text-text-secondary">Die Anmeldung öffnet am {{ $event->reg_opens_at->locale('de')->isoFormat('D. MMMM YYYY [um] HH:mm') }} Uhr.</p>
    @elseif ($state === 'geschlossen')
        <p class="mt-4 text-text-secondary">Die Anmeldung ist geschlossen.@if ($event->contact_email) Bei Fragen melde dich bei <a href="{{ (! $ohneLinks && \App\Support\Mailformular::aktiv()) ? '#frage' : 'mailto:'.$event->contact_email }}" class="text-dpsg-rot underline">{{ $event->contact_email }}</a>.@endif</p>
    @elseif ($state === 'ausgebucht')
        <p class="mt-4 text-text-secondary">Leider sind alle Plätze vergeben.</p>
    @else
        @if ($state === 'warteliste')
            <p class="mt-4 border-l-4 border-dpsg-yellow bg-white p-4" role="status">Alle Plätze sind vergeben. Du kannst dich auf die Warteliste setzen lassen – wird ein Platz frei, rückst du automatisch nach.</p>
        @endif

        @if ($errors->any())
            <div class="mt-4 border-l-4 border-dpsg-rot bg-white p-4" role="alert">
                <p class="font-bold">Bitte prüfe deine Angaben.</p>
                <ul class="mt-1 list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('events.register', $event->slug) }}" class="mt-6 space-y-5" x-data="{ byGuardian: {{ old('by_guardian') ? 'true' : 'false' }} }" novalidate>
            @csrf
        @if ($einbetten)
            <input type="hidden" name="einbetten" value="1" />
        @endif
            <div class="hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
            </div>

            <p class="text-sm text-text-secondary">Pflichtfelder sind mit * markiert.</p>

            <fieldset class="space-y-4">
                <legend class="font-bold">{{ $event->adults_only ? 'Deine Angaben' : 'Teilnehmende Person' }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="block text-sm font-bold">Vorname *</label>
                        <input id="first_name" name="first_name" type="text" required autocomplete="given-name" value="{{ old('first_name') }}" class="{{ $input }}" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror />
                        @error('first_name')<p id="first_name-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-bold">Nachname *</label>
                        <input id="last_name" name="last_name" type="text" required autocomplete="family-name" value="{{ old('last_name') }}" class="{{ $input }}" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror />
                        @error('last_name')<p id="last_name-error" class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                    </div>
                </div>

                @if ($event->zuschussAktiv())
                    <p class="text-sm text-text-secondary">Für diese Veranstaltung beantragen wir Zuschüsse. Dafür brauchen wir Anschrift, Geburtsdatum und Geschlecht für die Teilnahmeliste.</p>
                @endif
                @foreach ($fields as $key => $field)
                    @continue($field['type'] === 'herkunft') {{-- gehört zum Feld „Stamm“ --}}
                    @if ($field['type'] === 'stamm')
                        @include('events.partials.stamm-felder', ['key' => $key, 'field' => $field])
                        @continue
                    @endif
                    @php($label = $field['label'].($field['required'] ? ' *' : ''))
                    <div>
                        <label id="{{ $key }}-label" for="{{ $key }}" class="block text-sm font-bold">{{ $label }}</label>
                        @switch($field['type'])
                            @case('year')
                                <input id="{{ $key }}" name="{{ $key }}" type="number" inputmode="numeric" min="{{ now()->year - 100 }}" max="{{ now()->year }}" @required($field['required']) value="{{ old($key) }}" class="{{ $input }} sm:max-w-40" aria-describedby="{{ $key }}-hint" />
                                <p id="{{ $key }}-hint" class="mt-1 text-xs text-text-secondary">Das Jahr reicht – wir fragen kein vollständiges Geburtsdatum ab.</p>
                                @break
                            @case('select')
                                <select id="{{ $key }}" name="{{ $key }}" @required($field['required']) class="{{ $input }}">
                                    <option value="">Bitte wählen</option>
                                    @foreach ($field['options'] as $value => $optionLabel)
                                        <option value="{{ $value }}" @selected(old($key) === $value)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case('textarea')
                                <textarea id="{{ $key }}" name="{{ $key }}" rows="3" maxlength="1000" @required($field['required']) class="{{ $input }}" aria-describedby="{{ $key }}-hint">{{ old($key) }}</textarea>
                                @unless ($field['eigen'] ?? false)
                                    <p id="{{ $key }}-hint" class="mt-1 text-xs text-text-secondary">Bitte keine Gesundheitsangaben hier eintragen – die klären wir bei Bedarf persönlich.</p>
                                @endunless
                                @break
                            @case('zahl')
                                <input id="{{ $key }}" name="{{ $key }}" type="number" inputmode="numeric" min="0" max="100000" step="1" @required($field['required']) value="{{ old($key) }}" class="{{ $input }} sm:max-w-40" />
                                @break
                            @case('mehrfach')
                                {{-- Mehrere Antworten möglich; Pflicht prüft der Server (mindestens eine). --}}
                                <div class="mt-1 space-y-2" role="group" aria-labelledby="{{ $key }}-label">
                                    @foreach ($field['options'] as $value => $optionLabel)
                                        <label class="flex items-start gap-2">
                                            <input @if ($loop->first) id="{{ $key }}" @endif type="checkbox" name="{{ $key }}[]" value="{{ $value }}" @checked(in_array($value, (array) old($key, []), true)) class="mt-1 h-4 w-4 shrink-0" />
                                            <span>{{ $optionLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error($key.'.*')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                                @break
                            @case('plz')
                                <input id="{{ $key }}" name="{{ $key }}" type="text" inputmode="numeric" autocomplete="postal-code" maxlength="10" @required($field['required']) value="{{ old($key) }}" class="{{ $input }} sm:max-w-40" />
                                @break
                            @case('strasse')
                                <input id="{{ $key }}" name="{{ $key }}" type="text" autocomplete="street-address" maxlength="150" @required($field['required']) value="{{ old($key) }}" class="{{ $input }}" />
                                @break
                            @case('date')
                                <input id="{{ $key }}" name="{{ $key }}" type="date" autocomplete="bday" max="{{ now()->toDateString() }}" @required($field['required']) value="{{ old($key) }}" class="{{ $input }} sm:max-w-52" />
                                @break
                            @case('wohnort')
                                <input id="{{ $key }}" name="{{ $key }}" type="text" autocomplete="address-level2" maxlength="150" @required($field['required']) value="{{ old($key) }}" class="{{ $input }}" />
                                @break
                            @case('tel')
                                <input id="{{ $key }}" name="{{ $key }}" type="tel" autocomplete="tel" @required($field['required']) value="{{ old($key) }}" class="{{ $input }}" />
                                @break
                            @case('days')
                                {{-- „Ganze Zeit“ oder einzelne Tage; die Tage klappen erst bei
                                     „nur an einzelnen Tagen“ auf (ohne JavaScript immer sichtbar). --}}
                                @php($days = $event->days())
                                <div class="mt-1 space-y-2" x-data="{ modus: @js(old($key.'_modus')) }">
                                    <label class="flex items-center gap-2">
                                        <input id="{{ $key }}" type="radio" name="{{ $key }}_modus" value="alle" x-model="modus" @checked(old($key.'_modus') === 'alle') @required($field['required']) />
                                        Die ganze Zeit ({{ \Illuminate\Support\Arr::first($days) }} bis {{ \Illuminate\Support\Arr::last($days) }})
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="{{ $key }}_modus" value="teilweise" x-model="modus" @checked(old($key.'_modus') === 'teilweise') />
                                        Nur an einzelnen Tagen
                                    </label>
                                    <fieldset class="ml-6 flex flex-wrap gap-2" x-show="modus === 'teilweise'" x-transition.opacity>
                                        <legend class="mb-1 text-xs text-text-secondary">An welchen Tagen bist du dabei?</legend>
                                        @foreach ($days as $date => $dayLabel)
                                            <label class="inline-flex items-center gap-2 border border-dpsg-mittelbeige bg-white px-3 py-1.5 text-sm has-[:checked]:border-dpsg-blau has-[:checked]:bg-dpsg-blau has-[:checked]:text-white">
                                                <input type="checkbox" name="{{ $key }}[]" value="{{ $date }}" @checked(in_array($date, (array) old($key, []), true)) class="accent-white" />
                                                {{ $dayLabel }}
                                            </label>
                                        @endforeach
                                    </fieldset>
                                </div>
                                @error($key.'_modus')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                                @break
                            @default
                                <input id="{{ $key }}" name="{{ $key }}" type="text" @required($field['required']) value="{{ old($key) }}" class="{{ $input }}" />
                        @endswitch
                        @if (($field['eigen'] ?? false) && filled($field['hint'] ?? null))
                            <p class="mt-1 text-xs text-text-secondary">{{ $field['hint'] }}</p>
                        @endif
                        @error($key)<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </fieldset>

            @if ($event->mehrere_personen)
                {{-- Familienanmeldung: weitere Personen mit denselben Angaben (docs/veranstaltungen.md).
                     Ohne JavaScript bleibt es bei einer Person. --}}
                @php($mitJahr = isset($fields['birth_year']))
                @php($jahrPflicht = $mitJahr && $fields['birth_year']['required'])
                @php($preise = $event->hatTeilnahmebeitrag() ? $event->preise() : [])
                <fieldset class="space-y-4 border-t border-dpsg-mittelbeige pt-5" x-cloak
                          x-data="{ weitere: @js(array_values((array) old('weitere', []))), max: {{ \App\Services\EventRegistrations::MAX_WEITERE }} }">
                    <legend class="font-bold">Weitere Personen anmelden</legend>
                    <p class="text-sm text-text-secondary">
                        Geschwister oder weitere Familienmitglieder? Trag sie hier ein, bis zu {{ \App\Services\EventRegistrations::MAX_WEITERE }} weitere. Die übrigen Angaben (z. B. Stamm, Anschrift, Telefon) übernehmen wir von der ersten Person; jede Person bekommt eine eigene Anmeldung.
                    </p>
                    <template x-for="(person, i) in weitere" :key="i">
                        <div class="relative border border-dpsg-mittelbeige bg-white p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-bold" x-text="'Person ' + (i + 2)"></p>
                                <button type="button" class="min-h-11 px-3 text-sm font-bold text-dpsg-rot underline" @click="weitere.splice(i, 1)" :aria-label="'Person ' + (i + 2) + ' entfernen'">Entfernen</button>
                            </div>
                            <div class="mt-2 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-bold" :for="'weitere-' + i + '-vorname'">Vorname *</label>
                                    <input type="text" required autocomplete="off" maxlength="100" class="{{ $input }}" :id="'weitere-' + i + '-vorname'" :name="'weitere[' + i + '][first_name]'" x-model="person.first_name" />
                                </div>
                                <div>
                                    <label class="block text-sm font-bold" :for="'weitere-' + i + '-nachname'">Nachname *</label>
                                    <input type="text" required autocomplete="off" maxlength="100" class="{{ $input }}" :id="'weitere-' + i + '-nachname'" :name="'weitere[' + i + '][last_name]'" x-model="person.last_name" />
                                </div>
                                @if ($mitJahr)
                                    <div>
                                        <label class="block text-sm font-bold" :for="'weitere-' + i + '-jahr'">{{ $fields['birth_year']['label'] }}{{ $jahrPflicht ? ' *' : '' }}</label>
                                        <input type="number" inputmode="numeric" min="{{ now()->year - 100 }}" max="{{ now()->year }}" @required($jahrPflicht) class="{{ $input }} sm:max-w-40" :id="'weitere-' + i + '-jahr'" :name="'weitere[' + i + '][birth_year]'" x-model="person.birth_year" />
                                    </div>
                                @endif
                                @if ($preise !== [])
                                    <div>
                                        <label class="block text-sm font-bold" :for="'weitere-' + i + '-preis'">Teilnahmebeitrag *</label>
                                        <select required class="{{ $input }}" :id="'weitere-' + i + '-preis'" :name="'weitere[' + i + '][preis]'" x-model="person.preis">
                                            <option value="">Bitte wählen</option>
                                            @foreach ($preise as $pi => $preis)
                                                <option value="{{ $pi }}">{{ $preis['bezeichnung'] }} – {{ \App\Support\Zahlungen::euro($preis['cent']) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </template>
                    <button type="button" x-show="weitere.length < max"
                            @click="weitere.push({ first_name: '', last_name: document.getElementById('last_name')?.value ?? '', birth_year: '', preis: '' }); $nextTick(() => document.getElementById('weitere-' + (weitere.length - 1) + '-vorname')?.focus())"
                            class="inline-flex min-h-11 items-center gap-2 border border-dpsg-blau bg-white px-4 py-2 font-bold text-dpsg-blau hover:bg-dpsg-blau hover:text-white">
                        <x-heroicon-o-user-plus class="h-5 w-5" aria-hidden="true" />
                        Weitere Person hinzufügen
                    </button>
                    @error('weitere')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                </fieldset>
            @endif

            @unless ($event->adults_only)
            <fieldset class="space-y-4 border-t border-dpsg-mittelbeige pt-5">
                <legend class="sr-only">Anmeldung durch Personensorgeberechtigte</legend>
                <label class="flex gap-3">
                    <input type="checkbox" name="by_guardian" value="1" x-model="byGuardian" class="mt-1 h-5 w-5 shrink-0" @checked(old('by_guardian')) />
                    <span><span class="font-bold">Ich melde als personensorgeberechtigte Person (z. B. Elternteil) an.</span><br /><span class="text-sm text-text-secondary">Für Teilnehmende unter {{ $guardianAge }} Jahren ist das Pflicht.</span></span>
                </label>
                @error('by_guardian')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                <div x-show="byGuardian" x-cloak class="space-y-4 border-l-4 border-dpsg-hellbeige pl-4">
                    <div>
                        <label for="guardian_name" class="block text-sm font-bold">Dein Name (personensorgeberechtigte Person) *</label>
                        <input id="guardian_name" name="guardian_name" type="text" autocomplete="name" value="{{ old('guardian_name') }}" class="{{ $input }}" />
                        @error('guardian_name')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex gap-3 text-sm">
                        <input type="checkbox" name="guardian_consent" value="1" class="mt-1 h-5 w-5 shrink-0" @checked(old('guardian_consent')) />
                        <span>{{ config('dpsg.event_consent.guardian_text') }} *</span>
                    </label>
                    @error('guardian_consent')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                </div>
            </fieldset>
            @endunless

            @if ($event->hatTeilnahmebeitrag())
                <fieldset class="space-y-2 border-t border-dpsg-mittelbeige pt-5" @error('preis') aria-describedby="preis-error" @enderror>
                    <legend class="font-bold">Teilnahmebeitrag *</legend>
                    @foreach ($event->preise() as $i => $preis)
                        <label class="flex items-center gap-3">
                            <input type="radio" name="preis" value="{{ $i }}" required class="h-5 w-5 shrink-0" @checked((string) old('preis', count($event->preise()) === 1 ? '0' : '') === (string) $i) />
                            <span>{{ $preis['bezeichnung'] }} – <strong>{{ \App\Support\Zahlungen::euro($preis['cent']) }}</strong></span>
                        </label>
                    @endforeach
                    @if (\App\Support\Teilnahmebeitraege::aktiv())
                        <p class="text-xs text-text-secondary">Nach der Bestätigung deines Platzes bekommst du eine Zahlungsaufforderung mit Kontodaten und QR-Code für die Banking-App.</p>
                    @endif
                    @error('preis')<p id="preis-error" class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                </fieldset>
            @endif

            <div class="border-t border-dpsg-mittelbeige pt-5">
                <label for="email" class="block text-sm font-bold">E-Mail-Adresse *</label>
                <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="{{ $input }}" aria-describedby="email-hint" />
                <p id="email-hint" class="mt-1 text-xs text-text-secondary">Hierhin schicken wir den Bestätigungslink und alle Infos zur Anmeldung.</p>
                @error('email')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
            </div>

            <label class="flex gap-3 text-sm">
                <input type="checkbox" name="consent" value="1" required class="mt-1 h-5 w-5 shrink-0" @checked(old('consent')) />
                <span>{{ $event->consentText() }} Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline" @if ($einbetten) target="_blank" rel="noopener" @endif>Datenschutzerklärung</a>. *</span>
            </label>
            @error('consent')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror

            <button type="submit" class="bg-dpsg-blau px-6 py-3 font-bold text-white hover:bg-dpsg-rot focus:outline-2 focus:outline-offset-2 focus:outline-dpsg-blau">
                {{ $state === 'warteliste' ? 'Auf die Warteliste' : 'Verbindlich anmelden' }}
            </button>
        </form>
    @endif
</section>
