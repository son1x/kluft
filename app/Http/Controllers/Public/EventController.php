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
            'staemme' => isset($event->formFields()['stamm'])
                ? Gruppierung::query()->where('typ', 'stamm')->where('active', true)->orderBy('name')->pluck('name')
                : collect(),
        ]);
    }

    public function register(Request $request, string $slug, EventRegistrations $registrations): RedirectResponse
    {
        $event = Event::query()->visible()->where('slug', $slug)->firstOrFail();
        // Erst beim Zurückgeben bauen: with() schreibt sofort in die Session.
        $done = fn () => redirect()->route('events.show', $event->slug)->with('anmeldung', 'angefragt')->withFragment('anmeldung');

        // Honeypot: Bots bekommen dieselbe Erfolgsmeldung, es passiert nichts.
        if (filled($request->input('website'))) {
            return $done();
        }

        if (! $event->acceptsRegistrations()) {
            return redirect()->route('events.show', $event->slug)->withFragment('anmeldung');
        }

        $fields = $event->formFields();
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'by_guardian' => ['boolean'],
            'guardian_name' => ['nullable', 'required_if_accepted:by_guardian', 'string', 'max:200'],
            'guardian_consent' => ['accepted_if:by_guardian,1'],
            'consent' => ['accepted'],
        ];

        foreach ($fields as $key => $field) {
            $base = [$field['required'] ? 'required' : 'nullable'];
            $rules[$key] = [...$base, ...match ($field['type']) {
                'year' => ['integer', 'between:'.(now()->year - 100).','.now()->year],
                'select' => [Rule::in(array_keys($field['options'] ?? []))],
                'tel' => ['string', 'max:40', 'regex:/^[0-9 +()\/-]+$/'],
                'textarea' => ['string', 'max:1000'],
                default => ['string', 'max:150'],
            }];
        }

        $validator = ValidatorFactory::make($request->all(), $rules, [
            'required' => 'Bitte fülle dieses Feld aus.',
            'required_if_accepted' => 'Bitte gib deinen Namen als personensorgeberechtigte Person an.',
            'accepted_if' => 'Bitte bestätige die Einwilligung als personensorgeberechtigte Person.',
            'consent.accepted' => 'Bitte bestätige die Einwilligung zur Datenverarbeitung.',
            'email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'between' => 'Bitte gib ein gültiges Geburtsjahr an.',
            'regex' => 'Bitte nur Ziffern, Leerzeichen und + ( ) / - verwenden.',
        ]);

        // Unter 16 nur mit Einwilligung der Personensorgeberechtigten (F-EV-14).
        $validator->after(function (Validator $validator) use ($event, $request) {
            $year = is_numeric($request->input('birth_year')) ? (int) $request->input('birth_year') : null;

            if ($event->mayBeUnderConsentAge($year) && ! $request->boolean('by_guardian')) {
                $validator->errors()->add('by_guardian', 'Für Teilnehmende unter '.config('dpsg.guardian_consent_age').' Jahren muss eine personensorgeberechtigte Person die Anmeldung vornehmen.');
            }
        });

        $data = $validator->validate();

        $registrations->register($event, [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'birth_year' => $data['birth_year'] ?? null,
            'data' => collect($fields)->keys()->reject(fn ($k) => $k === 'birth_year')
                ->mapWithKeys(fn ($k) => [$k => $data[$k] ?? null])->all(),
            'guardian_name' => $request->boolean('by_guardian') ? $data['guardian_name'] : null,
            'guardian_consent' => $request->boolean('by_guardian') && $request->boolean('guardian_consent'),
        ]);

        return $done();
    }
}
