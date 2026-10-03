@extends('layouts.app')

@section('seo_title', $event->title.' – Veranstaltungen – DPSG Diözesanverband Trier')
@section('seo_description', (string) ($event->teaser ?: $event->title.' – '.$event->dateLabel()))

@php
    $input = 'mt-1 w-full border border-dpsg-mittelbeige bg-white px-3 py-2 font-normal focus:outline-2 focus:outline-dpsg-blau';
    $guardianAge = config('dpsg.guardian_consent_age');
@endphp

@section('content')
    <article class="mx-auto max-w-6xl px-4 py-10">
        <nav aria-label="Brotkrumen" class="mb-4 text-sm text-text-secondary">
            <a href="{{ route('events.index') }}" class="hover:underline">Veranstaltungen</a> › <span>{{ $event->title }}</span>
        </nav>

        <div class="grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="flex flex-wrap gap-2 text-xs font-bold">
                    @if ($event->stage)
                        @include('partials.stufe-badge', ['stage' => $event->stage])
                    @else
                        <span class="bg-dpsg-hellbeige px-2 py-1">{{ $event->scopeLabel() }}</span>
                    @endif
                    @if ($event->target_group)
                        <span class="bg-surface-muted px-2 py-1">{{ $event->target_group }}</span>
                    @endif
                </div>
                <h1 class="mt-3 font-serif text-3xl font-bold">{{ $event->title }}</h1>

                @if ($state === 'abgesagt')
                    <p class="mt-4 border-l-4 border-dpsg-rot bg-white p-4 font-bold" role="status">Diese Veranstaltung ist abgesagt.</p>
                @endif

                @if ($event->coverMedia)
                    @include('partials.cover-figure', ['media' => $event->coverMedia])
                @endif

                @if ($event->teaser)
                    <p class="mt-6 text-lg">{{ $event->teaser }}</p>
                @endif
                <div class="prose mt-4 max-w-none">{!! str($event->description ?? '')->sanitizeHtml()->glossar() !!}</div>
            </div>

            <aside class="self-start bg-dpsg-hellbeige p-6" aria-labelledby="eckdaten">
                <h2 id="eckdaten" class="font-serif text-xl font-bold">Auf einen Blick</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-bold">Wann</dt><dd>{{ $event->dateLabel() }}</dd></div>
                    @if ($event->location)<div><dt class="font-bold">Wo</dt><dd>{{ $event->location }}</dd></div>@endif
                    @if ($event->cost_info)<div><dt class="font-bold">Kosten</dt><dd>{{ $event->cost_info }}</dd></div>@endif
                    @if ($event->capacity)<div><dt class="font-bold">Plätze</dt><dd>{{ $event->capacity }} @if ($state === 'offen')(noch {{ $event->spotsLeft() }} frei)@endif</dd></div>@endif
                    <div><dt class="font-bold">Anmeldung</dt><dd>
                        @if ($event->reg_opens_at?->isFuture()) ab {{ $event->reg_opens_at->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr, @endif
                        bis {{ $event->registrationClosesAt()->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr
                    </dd></div>
                    @if ($event->contact_name || $event->contact_email)
                        <div><dt class="font-bold">Kontakt</dt><dd>{{ $event->contact_name }}@if ($event->contact_email)<br /><a href="mailto:{{ $event->contact_email }}" class="text-dpsg-rot underline">{{ $event->contact_email }}</a>@endif</dd></div>
                    @endif
                </dl>
            </aside>
        </div>

        <section id="anmeldung" aria-labelledby="anmeldung-heading" class="mt-12 max-w-3xl scroll-mt-24">
            <h2 id="anmeldung-heading" class="font-serif text-2xl font-bold">Anmeldung</h2>
            <p class="mt-1 text-sm text-text-secondary">Schon angemeldet? Unter <a href="{{ route('participants.index') }}" class="underline">Meine Anmeldungen</a> kannst du stornieren und Unterlagen abrufen.</p>

            @if (session('anmeldung') === 'angefragt')
                <div class="mt-4 border-l-4 border-stufe-pfadi bg-white p-4 shadow" role="status">
                    <p class="font-bold">Fast geschafft!</p>
                    <p class="mt-1">Wir haben dir eine E-Mail geschickt. Bitte bestätige die Anmeldung innerhalb von 48 Stunden über den Link darin – erst dann ist sie gültig. Schau auch im Spam-Ordner nach.</p>
                </div>
            @elseif ($state === 'abgesagt')
                <p class="mt-4 text-text-secondary">Die Veranstaltung ist abgesagt, eine Anmeldung ist nicht möglich.</p>
            @elseif ($state === 'bald')
                <p class="mt-4 text-text-secondary">Die Anmeldung öffnet am {{ $event->reg_opens_at->locale('de')->isoFormat('D. MMMM YYYY [um] HH:mm') }} Uhr.</p>
            @elseif ($state === 'geschlossen')
                <p class="mt-4 text-text-secondary">Die Anmeldung ist geschlossen.@if ($event->contact_email) Bei Fragen melde dich bei <a href="mailto:{{ $event->contact_email }}" class="text-dpsg-rot underline">{{ $event->contact_email }}</a>.@endif</p>
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
                    <div class="hidden" aria-hidden="true">
                        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
                    </div>

                    <p class="text-sm text-text-secondary">Pflichtfelder sind mit * markiert.</p>

                    <fieldset class="space-y-4">
                        <legend class="font-bold">Teilnehmende Person</legend>
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

                        @foreach ($fields as $key => $field)
                            @php($label = $field['label'].($field['required'] ? ' *' : ''))
                            <div>
                                <label for="{{ $key }}" class="block text-sm font-bold">{{ $label }}</label>
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
                                        <p id="{{ $key }}-hint" class="mt-1 text-xs text-text-secondary">Bitte keine Gesundheitsangaben hier eintragen – die klären wir bei Bedarf persönlich.</p>
                                        @break
                                    @case('tel')
                                        <input id="{{ $key }}" name="{{ $key }}" type="tel" autocomplete="tel" @required($field['required']) value="{{ old($key) }}" class="{{ $input }}" />
                                        @break
                                    @case('stamm')
                                        {{-- Autovervollständigung mit Stämmen und Bezirken von der Karte
                                             (Suche auch nach Ort und Bezirk). Ohne JavaScript bleibt die
                                             einfache Vorschlagsliste des Browsers; freie Eingabe ist immer möglich. --}}
                                        <div class="relative" x-data="stammAutocomplete(@js($gruppierungen), @js(old($key, '')))" @click.outside="open = false">
                                            <input id="{{ $key }}" name="{{ $key }}" type="text" list="gruppierungen" autocomplete="off" @required($field['required']) value="{{ old($key) }}" class="{{ $input }}"
                                                   x-init="$el.removeAttribute('list')" x-model="query"
                                                   role="combobox" aria-autocomplete="list" aria-controls="{{ $key }}-vorschlaege" :aria-expanded="open.toString()"
                                                   :aria-activedescendant="active >= 0 ? '{{ $key }}-vorschlag-' + active : null"
                                                   @input="open = true; active = -1" @focus="open = query.length > 0"
                                                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                                                   @keydown.enter="if (open && active >= 0) { $event.preventDefault(); choose(results[active]) }"
                                                   @keydown.escape="open = false" />
                                            <ul id="{{ $key }}-vorschlaege" role="listbox" x-show="open && results.length" x-cloak
                                                class="absolute z-20 mt-1 max-h-72 w-full overflow-auto border border-dpsg-mittelbeige bg-white shadow-lg">
                                                <template x-for="(option, i) in results" :key="option.value">
                                                    <li :id="'{{ $key }}-vorschlag-' + i" role="option" :aria-selected="(i === active).toString()"
                                                        @mousedown.prevent="choose(option)" @mouseenter="active = i"
                                                        :class="i === active ? 'bg-dpsg-blau text-white' : ''" class="cursor-pointer px-3 py-2">
                                                        <span class="block font-bold" x-text="option.value"></span>
                                                        <span class="block text-xs" :class="i === active ? 'text-white/80' : 'text-text-secondary'" x-text="option.hint"></span>
                                                    </li>
                                                </template>
                                            </ul>
                                            <datalist id="gruppierungen">@foreach ($gruppierungen as $option)<option value="{{ $option['value'] }}">{{ $option['hint'] }}</option>@endforeach</datalist>
                                        </div>
                                        <p class="mt-1 text-xs text-text-secondary">Tippe den Namen, den Ort oder den Bezirk – oder trag etwas anderes ein.</p>
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
                                @error($key)<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </fieldset>

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

                    <div class="border-t border-dpsg-mittelbeige pt-5">
                        <label for="email" class="block text-sm font-bold">E-Mail-Adresse *</label>
                        <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="{{ $input }}" aria-describedby="email-hint" />
                        <p id="email-hint" class="mt-1 text-xs text-text-secondary">Hierhin schicken wir den Bestätigungslink und alle Infos zur Anmeldung.</p>
                        @error('email')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex gap-3 text-sm">
                        <input type="checkbox" name="consent" value="1" required class="mt-1 h-5 w-5 shrink-0" @checked(old('consent')) />
                        <span>{{ __(config('dpsg.event_consent.text'), ['tage' => $event->retention_days]) }} Mehr in der <a href="{{ url('/datenschutz') }}" class="text-dpsg-rot underline">Datenschutzerklärung</a>. *</span>
                    </label>
                    @error('consent')<p class="text-sm text-dpsg-rot">{{ $message }}</p>@enderror

                    <button type="submit" class="bg-dpsg-blau px-6 py-3 font-bold text-white hover:bg-dpsg-rot focus:outline-2 focus:outline-offset-2 focus:outline-dpsg-blau">
                        {{ $state === 'warteliste' ? 'Auf die Warteliste' : 'Verbindlich anmelden' }}
                    </button>
                </form>
            @endif
        </section>

        {{-- Frage zur Veranstaltung (F-CMS-09): geht per Mail an die Kontaktperson --}}
        <section id="frage" aria-labelledby="frage-heading" class="mt-12 max-w-3xl scroll-mt-24">
            <h2 id="frage-heading" class="font-serif text-2xl font-bold">Fragen zur Veranstaltung?</h2>
            @if (session('frage_status') === 'gesendet')
                <div class="mt-4 border-l-4 border-stufe-pfadi bg-white p-4 shadow" role="status">
                    <p class="font-bold">Danke für deine Frage!</p>
                    <p class="mt-1">Sie ist unterwegs zu {{ $event->contact_name ?: 'den Verantwortlichen' }}. Die Antwort kommt per E-Mail.</p>
                </div>
            @else
                <details class="mt-4 bg-white p-5 shadow-md" @if ($errors->frage->any() || session('frage_status')) open @endif>
                    <summary class="cursor-pointer font-bold text-dpsg-rot">Frage an {{ $event->contact_name ?: 'die Verantwortlichen' }} schreiben</summary>
                    @if (session('frage_status') === 'fehler')
                        <p class="mt-4 border-l-4 border-dpsg-rot bg-surface-muted p-3 text-sm" role="alert">Das hat leider nicht geklappt. Bitte versuch es später noch einmal oder schreib direkt an <a href="mailto:{{ $event->contact_email ?: config('dpsg.contact.email') }}" class="text-dpsg-rot underline">{{ $event->contact_email ?: config('dpsg.contact.email') }}</a>.</p>
                    @elseif (session('frage_status') === 'abgelaufen')
                        <p class="mt-4 border-l-4 border-dpsg-mittelbeige bg-surface-muted p-3 text-sm" role="alert">Das Formular war sehr lange geöffnet. Bitte schick es noch einmal ab.</p>
                    @endif
                    <form method="post" action="{{ route('events.ask', $event->slug) }}" class="mt-4 space-y-4" novalidate>
                        @include('partials.message-form-fields', ['idPrefix' => 'frage', 'bag' => 'frage'])
                        <button type="submit" class="inline-flex items-center gap-2 bg-dpsg-blau px-5 py-2 font-bold text-white hover:bg-dpsg-rot">
                            <x-heroicon-o-paper-airplane class="h-5 w-5" aria-hidden="true" /> Frage senden
                        </button>
                    </form>
                </details>
            @endif
        </section>
    </article>
@endsection
