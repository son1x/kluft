<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Gruppierung;
use App\Services\EventRegistrations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Veranstaltungsübersicht, Detailseite und Anmeldung (F-EV-10..16).
 */
class EventController extends Controller
{
    public function index(Request $request): View
    {
        $stufe = $request->string('stufe')->toString();
        $stufe = array_key_exists($stufe, config('dpsg.stages')) ? $stufe : null;

        return view('events.index', [
            'events' => Event::query()->visible()->upcoming()
                ->when($stufe, fn ($q) => $q->where('stage', $stufe))
                ->with('coverMedia')
                ->orderBy('starts_at')
                ->get(),
            'stufe' => $stufe,
        ]);
    }

    public function show(string $slug): View
    {
        $event = Event::query()->visible()->with('coverMedia')->where('slug', $slug)->firstOrFail();

        return view('events.show', [
            'event' => $event,
            'state' => $event->registrationState(),
            'fields' => $event->formFields(),
            'gruppierungen' => isset($event->formFields()['stamm']) ? $this->gruppierungOptions() : [],
        ]);
    }

    /**
     * Nur das Anmeldeformular, zum Einbinden als iframe (z. B. auf der alten
     * Website vor dem Livegang). Nur bei „Vorab extern freigeben“; erlaubte
     * Websites stehen in dpsg.einbetten_von.
     */
    public function embed(Request $request, string $slug): View
    {
        $event = Event::query()->visible()->where('extern_freigegeben', true)->where('slug', $slug)->firstOrFail();
        $request->attributes->set('frame_ancestors', self::einbettenVon());

        return view('events.einbetten', [
            'event' => $event,
            'state' => $event->registrationState(),
            'fields' => $event->formFields(),
            'gruppierungen' => isset($event->formFields()['stamm']) ? $this->gruppierungOptions() : [],
            'einbetten' => true,
        ]);
    }

    /**
     * Gültige Ursprünge aus dpsg.einbetten_von (https, ohne Pfad).
     *
     * @return list<string>
     */
    public static function einbettenVon(): array
    {
        return array_values(array_filter(
            array_map(fn ($u) => rtrim(trim((string) $u), '/'), (array) config('dpsg.einbetten_von', [])),
            fn (string $u) => preg_match('#^https://[a-z0-9.-]+(:\d+)?$#i', $u) === 1,
        ));
    }

    public function register(Request $request, string $slug, EventRegistrations $registrations): RedirectResponse
    {
        $event = Event::query()->visible()->where('slug', $slug)->firstOrFail();
        $einbetten = $request->boolean('einbetten') && $event->extern_freigegeben;
        // Erst beim Zurückgeben bauen: with() schreibt sofort in die Session.
        $done = fn () => ($einbetten
            ? redirect()->route('events.embed', $event->slug)
            : redirect()->route('events.show', $event->slug)->withFragment('anmeldung'))->with('anmeldung', 'angefragt');

        // Honeypot: Bots bekommen dieselbe Erfolgsmeldung, es passiert nichts.
        if (filled($request->input('website'))) {
            return $done();
        }

        if (! $event->acceptsRegistrations()) {
            return $einbetten ? redirect()->route('events.embed', $event->slug) : redirect()->route('events.show', $event->slug)->withFragment('anmeldung');
        }

        $fields = $event->formFields();
        // Für Erwachsene: kein Abschnitt für Personensorgeberechtigte.
        if ($event->adults_only) {
            $request->request->remove('by_guardian');
        }
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'by_guardian' => ['boolean'],
            'guardian_name' => ['nullable', 'required_if_accepted:by_guardian', 'string', 'max:200'],
            'guardian_consent' => ['accepted_if:by_guardian,1'],
            'consent' => ['accepted'],
        ];
        // Teilnahmebeitrag: eine der Preisstufen wählen (Index in der Liste).
        $preise = $event->preise();
        if ($preise !== []) {
            $rules['preis'] = ['required', Rule::in(array_map('strval', array_keys($preise)))];
        }

        foreach ($fields as $key => $field) {
            // Tage: „ganze Zeit“ oder einzelne Tage (Checkboxen).
            if ($field['type'] === 'days') {
                $rules["{$key}_modus"] = [$field['required'] ? 'required' : 'nullable', Rule::in(['alle', 'teilweise'])];
                $rules[$key] = ['nullable', 'array', "required_if:{$key}_modus,teilweise"];
                $rules["{$key}.*"] = [Rule::in(array_keys($event->days()))];

                continue;
            }
            // Eigenes Feld mit mehreren Antworten: Liste aus den angebotenen Möglichkeiten.
            if ($field['type'] === 'mehrfach') {
                $rules[$key] = [$field['required'] ? 'required' : 'nullable', 'array'];
                $rules["{$key}.*"] = [Rule::in(array_keys($field['options'] ?? []))];

                continue;
            }
            $base = [$field['required'] ? 'required' : 'nullable'];
            $rules[$key] = [...$base, ...match ($field['type']) {
                'year' => ['integer', 'between:'.(now()->year - 100).','.now()->year],
                'select', 'herkunft' => [Rule::in(array_keys($field['options'] ?? []))],
                'tel' => ['string', 'max:40', 'regex:/^[0-9 +()\/-]+$/'],
                'plz' => ['string', 'max:10', 'regex:/^([A-Za-z]{1,2}[- ]?)?[0-9]{4,5}$/'],
                'date' => ['date_format:Y-m-d', 'before_or_equal:today', 'after:'.now()->subYears(100)->toDateString()],
                'textarea' => ['string', 'max:1000'],
                'zahl' => ['integer', 'between:0,100000'],
                default => ['string', 'max:150'],
            }];
        }

        // Freiwillige Foto-Einwilligung: nur wenn eingeschaltet, Antwort ja oder nein.
        if ($event->foto_einwilligung) {
            $rules['foto'] = ['required', Rule::in(['ja', 'nein'])];
        }

        // Familienanmeldung: weitere Personen; leere Zeilen zählen nicht.
        if ($event->mehrere_personen) {
            $weitere = array_values(array_filter((array) $request->input('weitere', []),
                fn ($p) => is_array($p) && collect($p)->except(['preis', 'foto'])->contains(fn ($v) => filled($v))));
            $request->merge(['weitere' => $weitere]);
            $rules['weitere'] = ['array', 'max:'.EventRegistrations::MAX_WEITERE];
            $rules['weitere.*.first_name'] = ['required', 'string', 'max:100'];
            $rules['weitere.*.last_name'] = ['required', 'string', 'max:100'];
            $rules['weitere.*.birth_year'] = [($fields['birth_year']['required'] ?? false) ? 'required' : 'nullable', 'integer', 'between:'.(now()->year - 100).','.now()->year];
            if ($preise !== []) {
                $rules['weitere.*.preis'] = ['required', Rule::in(array_map('strval', array_keys($preise)))];
            }
            if ($event->foto_einwilligung) {
                $rules['weitere.*.foto'] = ['required', Rule::in(['ja', 'nein'])];
            }
        } else {
            $request->request->remove('weitere');
        }

        $validator = ValidatorFactory::make($request->all(), $rules, [
            'weitere.max' => 'Höchstens '.EventRegistrations::MAX_WEITERE.' weitere Personen auf einmal.',
            'weitere.*.first_name.required' => 'Bitte gib bei jeder weiteren Person den Vornamen an.',
            'weitere.*.last_name.required' => 'Bitte gib bei jeder weiteren Person den Nachnamen an.',
            'weitere.*.birth_year.required' => 'Bitte gib bei jeder weiteren Person das Geburtsjahr an.',
            'weitere.*.preis.required' => 'Bitte wähle bei jeder weiteren Person den Teilnahmebeitrag.',
            ...collect($fields)->filter(fn (array $f) => $f['type'] === 'zahl')
                ->mapWithKeys(fn (array $f, string $k) => ["{$k}.between" => 'Bitte gib eine Zahl von 0 bis 100.000 an.'])->all(),
            'required' => 'Bitte fülle dieses Feld aus.',
            'required_if_accepted' => 'Bitte gib deinen Namen als personensorgeberechtigte Person an.',
            'accepted_if' => 'Bitte bestätige die Einwilligung als personensorgeberechtigte Person.',
            'consent.accepted' => 'Bitte bestätige die Einwilligung zur Datenverarbeitung.',
            'email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'between' => 'Bitte gib ein gültiges Geburtsjahr an.',
            'regex' => 'Bitte nur Ziffern, Leerzeichen und + ( ) / - verwenden.',
            'plz.regex' => 'Bitte gib eine gültige Postleitzahl an (4 oder 5 Ziffern).',
            'required_if' => 'Bitte wähle mindestens einen Tag aus.',
            'in' => 'Bitte wähle eine der angebotenen Möglichkeiten.',
            'integer' => 'Bitte gib eine ganze Zahl an.',
            'preis.required' => 'Bitte wähle den passenden Teilnahmebeitrag.',
            'foto.required' => 'Bitte sag uns, ob wir Fotos zeigen dürfen (Ja oder Nein).',
            'weitere.*.foto.required' => 'Bitte gib bei jeder weiteren Person an, ob wir Fotos zeigen dürfen.',
            'date_format' => 'Bitte gib ein gültiges Datum an.',
            'before_or_equal' => 'Bitte gib ein gültiges Geburtsdatum an.',
            'after' => 'Bitte gib ein gültiges Geburtsdatum an.',
        ]);

        // Zuschussantrag: Geburtsjahr aus dem Geburtsdatum (für die Altersprüfung unten).
        if (! isset($fields['birth_year']) && preg_match('/^(\d{4})-\d{2}-\d{2}$/', (string) $request->input('geburtsdatum'), $treffer)) {
            $request->merge(['birth_year' => (int) $treffer[1]]);
        }

        // Unter 16 nur mit Einwilligung der Personensorgeberechtigten (F-EV-14).
        $validator->after(function (Validator $validator) use ($event, $request) {
            $year = is_numeric($request->input('birth_year')) ? (int) $request->input('birth_year') : null;

            if ($event->adults_only && $event->mayBeUnderConsentAge($year)) {
                $validator->errors()->add('birth_year', 'Diese Veranstaltung ist für Erwachsene; anmelden können sich Personen ab '.config('dpsg.guardian_consent_age').' Jahren.');
            } elseif ($event->mayBeUnderConsentAge($year) && ! $request->boolean('by_guardian')) {
                $validator->errors()->add('by_guardian', 'Für Teilnehmende unter '.config('dpsg.guardian_consent_age').' Jahren muss eine personensorgeberechtigte Person die Anmeldung vornehmen.');
            }
            // Dieselben Regeln für weitere Personen (Familienanmeldung).
            foreach ((array) $request->input('weitere', []) as $i => $person) {
                $jahr = is_numeric($person['birth_year'] ?? null) ? (int) $person['birth_year'] : null;
                if ($event->adults_only && $event->mayBeUnderConsentAge($jahr)) {
                    $validator->errors()->add("weitere.{$i}.birth_year", 'Person '.($i + 2).': Diese Veranstaltung ist für Erwachsene ab '.config('dpsg.guardian_consent_age').' Jahren.');
                } elseif ($event->mayBeUnderConsentAge($jahr) && ! $request->boolean('by_guardian') && ! $validator->errors()->has('by_guardian')) {
                    $validator->errors()->add('by_guardian', 'Für Teilnehmende unter '.config('dpsg.guardian_consent_age').' Jahren muss eine personensorgeberechtigte Person die Anmeldung vornehmen.');
                }
            }
        });

        $data = $validator->validate();

        $registrations->register($event, [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'birth_year' => $data['birth_year'] ?? (isset($data['geburtsdatum']) ? (int) substr((string) $data['geburtsdatum'], 0, 4) : null),
            'data' => collect($fields)->except('birth_year')
                ->map(fn (array $field, string $k) => $field['type'] === 'days' ? $this->selectedDays($event, $data, $k) : ($data[$k] ?? null))
                ->all(),
            'guardian_name' => $request->boolean('by_guardian') ? $data['guardian_name'] : null,
            'guardian_consent' => $request->boolean('by_guardian') && $request->boolean('guardian_consent'),
            'preis' => $preise !== [] ? $preise[(int) $data['preis']] : null,
            'foto_einwilligung' => isset($data['foto']) ? $data['foto'] === 'ja' : null,
        ], collect($data['weitere'] ?? [])->map(fn (array $p) => [
            'first_name' => $p['first_name'],
            'last_name' => $p['last_name'],
            'birth_year' => isset($p['birth_year']) ? (int) $p['birth_year'] : null,
            'preis' => $preise !== [] ? $preise[(int) $p['preis']] : null,
            'foto_einwilligung' => isset($p['foto']) ? $p['foto'] === 'ja' : null,
        ])->all());

        return $done();
    }

    /**
     * Gewählte Tage als Liste von Datumswerten; „ganze Zeit“ = alle Tage.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>|null
     */
    private function selectedDays(Event $event, array $data, string $key): ?array
    {
        return match ($data["{$key}_modus"] ?? null) {
            'alle' => array_keys($event->days()),
            'teilweise' => array_values(array_intersect(array_keys($event->days()), (array) ($data[$key] ?? []))),
            default => null,
        };
    }

    /**
     * Vorschläge für das Feld „Stamm“: alle aktiven Bezirke und Stämme von
     * der Karte; gesucht wird auch über Ort und Bezirk.
     *
     * @return list<array{value: string, hint: string, search: string}>
     */
    private function gruppierungOptions(): array
    {
        return Gruppierung::query()->active()->with('bezirk')->orderBy('typ')->orderBy('name')->get()
            ->map(fn (Gruppierung $g) => $g->typ === 'bezirk'
                ? ['value' => 'Bezirk '.$g->name, 'hint' => 'Bezirk', 'search' => 'Bezirk '.$g->name]
                : ['value' => $g->name, 'hint' => 'Stamm'.($g->bezirk ? ' · Bezirk '.$g->bezirk->name : ''),
                    'search' => implode(' ', array_filter([$g->name, $g->ort, $g->bezirk?->name]))])
            ->values()->all();
    }
}
