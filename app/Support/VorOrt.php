<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\VorOrtEreignis;
use App\Services\EventRegistrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vor Ort (docs/vor-ort.md): Daten für Tablet und Handy am
 * Veranstaltungsort und Verarbeitung der Ereignisse, die die Geräte
 * schicken. Rechte wie die Teilnehmendenliste (manageParticipants).
 */
class VorOrt
{
    /**
     * Zahlungsarten beim Kassieren vor Ort (config dpsg.vor_ort_zahlwege).
     *
     * @return array<string, string>
     */
    public static function zahlwege(): array
    {
        return collect((array) config('dpsg.vor_ort_zahlwege'))
            ->filter(fn ($label, $key) => is_string($key) && preg_match('/^[a-z_]{1,20}$/', $key) && filled($label))
            ->map(fn ($label) => mb_substr((string) $label, 0, 40))->all();
    }

    /** Textversion bei Nachmeldung vor Ort (statt Online-Einwilligung). */
    public const NACHMELDUNG = 'vor-ort';

    /** Wie lange nach dem Ende eine Veranstaltung noch erscheint (Nachträge). */
    public const TAGE_DANACH = 7;

    /** Wie weit im Voraus eine Veranstaltung erscheint. */
    public const TAGE_VORHER = 60;

    public static function aktiv(): bool
    {
        return Features::enabled(Features::VOR_ORT);
    }

    /**
     * Veranstaltungen, die die Person vor Ort betreuen darf.
     *
     * @return Collection<int, Event>
     */
    public static function veranstaltungen(User $user): Collection
    {
        $heute = now()->startOfDay();

        return Event::query()
            ->where('status', '!=', 'draft')
            ->where('starts_at', '<=', $heute->copy()->addDays(self::TAGE_VORHER)->endOfDay())
            ->where(fn ($q) => $q->where('ends_at', '>=', $heute->copy()->subDays(self::TAGE_DANACH))
                ->orWhere(fn ($q) => $q->whereNull('ends_at')->where('starts_at', '>=', $heute->copy()->subDays(self::TAGE_DANACH))))
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Event $e) => $user->can('manageParticipants', $e))
            ->values();
    }

    /** @return array<string, mixed> */
    public static function uebersicht(Event $event): array
    {
        $bestaetigt = $event->registrations()->where('status', 'confirmed');

        return [
            'id' => $event->getKey(),
            'titel' => $event->title,
            'datum' => $event->dateLabel(),
            'laeuft' => $event->starts_at->lte(now()) && ($event->ends_at ?? $event->starts_at->copy()->endOfDay())->gte(now()),
            'teilnehmende' => (clone $bestaetigt)->count(),
        ];
    }

    /**
     * Alles, was das Gerät für eine Veranstaltung braucht.
     *
     * @return array<string, mixed>
     */
    public static function daten(Event $event): array
    {
        $felder = $event->formFields();
        $tage = $event->days();
        if ($tage === []) {
            $tage = [$event->starts_at->toDateString() => $event->starts_at->locale('de')->isoFormat('dd, D.M.')];
        }
        $tageFeld = collect($felder)->search(fn (array $f) => $f['type'] === 'days');
        $beitrag = $event->hatTeilnahmebeitrag();

        $unterschrift = Unterschriften::moeglich($event);
        $personen = $event->registrations()->where('status', 'confirmed')->with(['zahlungen', 'unterschrift'])
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->map(function (EventRegistration $r) use ($event, $felder, $tage, $tageFeld, $beitrag, $unterschrift) {
                $data = (array) $r->data;
                $zahlung = $beitrag ? $r->zahlung() : null;

                return [
                    'id' => $r->getKey(),
                    'vorname' => $r->first_name,
                    'nachname' => $r->last_name,
                    'jahrgang' => $r->birth_year,
                    // Erwartete Tage: aus der Tage-Auswahl, sonst alle.
                    'erwartet' => $tageFeld !== false && filled($data[$tageFeld] ?? null)
                        ? array_values(array_intersect(array_keys($tage), (array) $data[$tageFeld]))
                        : array_keys($tage),
                    'da' => array_values((array) $r->vor_ort_tage),
                    'zusatz' => (object) array_intersect_key((array) $r->zusatz, $event->zusatzFelder()),
                    'unterschrieben' => $unterschrift ? $r->unterschrift?->unterschrieben_am?->toIso8601String() : null,
                    'zahlung' => $zahlung === null ? null : [
                        'stand' => $zahlung->status,
                        'offen_cent' => $zahlung->offenCent(),
                        'betrag_cent' => $zahlung->betrag_cent,
                    ],
                    'werte' => collect($felder)->mapWithKeys(fn (array $f, string $k) => [$k => $k === 'birth_year' ? $r->birth_year : ($data[$k] ?? null)])->all(),
                    'angaben' => collect($felder)
                        ->map(fn (array $f, string $k) => ['schluessel' => $k, 'label' => $f['label'], 'sensibel' => (bool) ($f['sensibel'] ?? false),
                            'wert' => $event->fieldValueLabel($f, $k === 'birth_year' ? $r->birth_year : ($data[$k] ?? null))])
                        ->filter(fn (array $a) => $a['wert'] !== null)->values()->all(),
                ];
            })->values();

        return [
            ...self::uebersicht($event),
            'tage' => collect($tage)->map(fn (string $label, string $tag) => ['tag' => $tag, 'label' => $label])->values()->all(),
            'heute' => now()->toDateString(),
            'beitrag' => $beitrag,
            // Nachmelden vor Ort: Preisstufen zur Auswahl (Reihenfolge = Nummer im Ereignis).
            'preise' => collect($event->preise())->map(fn (array $p, int $i) => ['nr' => $i, 'bezeichnung' => $p['bezeichnung'], 'cent' => $p['cent']])->values()->all(),
            'nachmelden' => $event->status !== 'draft',
            // Kassieren vor Ort: nur mit Teilnahmebeitrag und Funktion „Zahlungen“.
            'zahlwege' => $beitrag && Features::enabled(Features::ZAHLUNGEN) ? self::zahlwege() : (object) [],
            // Felder zum Korrigieren (Typ und Auswahl) – wie „Angaben bearbeiten“ im Backend.
            'felder' => collect($felder)->map(fn (array $f, string $k) => [
                'schluessel' => $k, 'label' => $f['label'], 'typ' => $f['type'], 'sensibel' => (bool) ($f['sensibel'] ?? false), 'pflicht' => (bool) ($f['required'] ?? false),
                'optionen' => match ($f['type']) {
                    'days' => $tage,
                    'select', 'herkunft', 'mehrfach' => (array) ($f['options'] ?? []),
                    default => null,
                },
            ])->values()->all(),
            // Digitale Unterschrift (Stufe 2): nur mit Zuschussantrag.
            'unterschrift' => $unterschrift ? [
                'text' => Unterschriften::text($event),
                'festgeschrieben_am' => $event->teilnahmeliste_am?->toIso8601String(),
            ] : null,
            // Zusatzinfos fürs Team (z. B. Zimmer); gleiche Werte = gleiche Gruppe.
            'zusatz_felder' => collect($event->zusatzFelder())->map(fn (string $label, string $k) => ['schluessel' => $k, 'label' => $label])->values()->all(),
            'verpflegung' => self::verpflegung($event, $felder, $personen->all()),
            'personen' => $personen->all(),
        ];
    }

    /**
     * Verpflegung in Summe, z. B. „vegetarisch: 4“.
     *
     * @param  array<string, array<string, mixed>>  $felder
     * @param  list<array<string, mixed>>  $personen
     * @return list<array{label: string, anzahl: int}>
     */
    private static function verpflegung(Event $event, array $felder, array $personen): array
    {
        if (! isset($felder['diet'])) {
            return [];
        }

        return collect($personen)->countBy(fn (array $p) => $event->fieldValueLabel($felder['diet'], $p['werte']['diet'] ?? null) ?? 'keine Angabe')
            ->map(fn (int $n, string $label) => ['label' => $label, 'anzahl' => $n])->sortByDesc('anzahl')->values()->all();
    }

    /** Text des Bestätigungsdialogs „Anwesenheit übernehmen“ (mit Warnung bei festgeschriebener Liste). */
    public static function anwesenheitHinweis(Event $event): string
    {
        $bestaetigt = $event->registrations()->where('status', 'confirmed')->count();
        $abgehakt = $event->registrations()->where('status', 'confirmed')->whereNotNull('vor_ort_tage')->count();

        return "{$abgehakt} von {$bestaetigt} Teilnehmenden wurden unter „Vor Ort“ mindestens einmal als da abgehakt. "
            .'Sie gelten im Zuschussantrag an genau diesen Tagen als anwesend; bisherige Angaben zu ihnen werden ersetzt.'
            .($event->teilnahmeliste_am
                ? ' Achtung: Die Teilnahmeliste ist bereits festgeschrieben ('.$event->teilnahmeliste_am->format('d.m.Y H:i').'). '
                    .'Sie bleibt, wie sie ist – danach bitte „Teilnahmeliste neu festschreiben“ wählen.'
                : '');
    }

    /**
     * Anwesenheit aus „Vor Ort“ in den Zuschussantrag übernehmen: Wer
     * abgehakt wurde, gilt an diesen Tagen als anwesend. Wer nie abgehakt
     * wurde, bleibt unverändert – oder gilt auf Wunsch als nicht dabei.
     *
     * @return array{uebernommen: int, nicht_da: int, unveraendert: int}
     */
    public static function anwesenheitUebernehmen(Event $event, bool $fehlendeNichtDa = false): array
    {
        $alle = array_keys($event->days()) ?: [$event->starts_at->toDateString()];
        $zahl = ['uebernommen' => 0, 'nicht_da' => 0, 'unveraendert' => 0];

        foreach ($event->registrations()->where('status', 'confirmed')->get() as $r) {
            $tage = array_values(array_intersect($alle, (array) $r->vor_ort_tage));
            if ($tage !== []) {
                // Alle Tage = Standard, dann nichts speichern (wie im Zuschuss-Dialog).
                $r->update(['anwesend' => true, 'anwesend_tage' => count($alle) > 1 && $tage !== $alle ? $tage : null]);
                $zahl['uebernommen']++;
            } elseif ($fehlendeNichtDa) {
                $r->update(['anwesend' => false, 'anwesend_tage' => null]);
                $zahl['nicht_da']++;
            } else {
                $zahl['unveraendert']++;
            }
        }

        return $zahl;
    }

    /**
     * Ereignisse verarbeiten. Jede Kennung genau einmal; Ergebnis je Kennung:
     * „ok“, „doppelt“ (schon verbucht) oder „abgelehnt“ mit Grund.
     *
     * @param  list<array<string, mixed>>  $ereignisse
     * @return array<string, array{ergebnis: string, grund?: string}>
     */
    public static function verarbeiten(User $user, array $ereignisse): array
    {
        $ergebnisse = [];
        foreach ($ereignisse as $e) {
            $uuid = (string) ($e['id'] ?? '');
            if (! Str::isUuid($uuid)) {
                continue;
            }
            $ergebnisse[$uuid] = DB::transaction(fn () => self::eines($user, $uuid, $e));
        }

        return $ergebnisse;
    }

    /**
     * @param  array<string, mixed>  $e
     * @return array{ergebnis: string, grund?: string}
     */
    private static function eines(User $user, string $uuid, array $e): array
    {
        if (VorOrtEreignis::query()->where('uuid', $uuid)->exists()) {
            return ['ergebnis' => 'doppelt'];
        }

        $typ = (string) ($e['typ'] ?? '');
        $daten = (array) ($e['daten'] ?? []);
        if ($typ === 'nachmeldung') {
            return self::nachmeldung($user, $uuid, $e, $daten);
        }
        $registration = EventRegistration::query()->with('event')->find(self::anmeldungsId($e['anmeldung'] ?? null));
        $event = $registration?->event;

        $grund = match (true) {
            ! in_array($typ, VorOrtEreignis::TYPEN, true) => 'Unbekannte Art.',
            $registration === null || $event === null => 'Anmeldung nicht gefunden.',
            ! $user->can('manageParticipants', $event) => 'Keine Berechtigung für diese Veranstaltung.',
            $registration->status !== 'confirmed' => 'Die Anmeldung ist nicht (mehr) bestätigt.',
            default => match ($typ) {
                'anwesend' => self::anwesend($registration, $daten),
                'zahlung' => self::zahlung($user, $registration, $daten),
                'angaben' => self::angaben($registration, $daten),
                'zusatz' => self::zusatz($registration, $daten),
                'unterschrift' => self::unterschrift($user, $registration, $daten, $e['zeit'] ?? null),
            },
        };

        // Ohne bekannte Anmeldung gibt es nichts zu protokollieren.
        if ($registration === null || $event === null) {
            return ['ergebnis' => 'abgelehnt', 'grund' => $grund ?? 'Anmeldung nicht gefunden.'];
        }

        $erfasst = isset($e['zeit']) ? rescue(fn () => Carbon::parse((string) $e['zeit']), null, false) : null;
        VorOrtEreignis::query()->create([
            'uuid' => $uuid,
            'event_id' => $event->getKey(),
            'event_registration_id' => $registration->getKey(),
            'user_id' => $user->getKey(),
            'typ' => mb_substr($typ, 0, 30),
            'daten' => self::protokollDaten($typ, $daten, $event),
            'ergebnis' => $grund === null ? 'ok' : 'abgelehnt',
            'erfasst_am' => $erfasst,
        ]);

        if ($grund === null && in_array($typ, ['zahlung', 'angaben', 'zusatz', 'unterschrift'], true)) {
            AuditLogger::log("vor_ort.{$typ}:".$registration->getKey(), $user, Event::class, $event->getKey(), request());
        }

        return $grund === null ? ['ergebnis' => 'ok'] : ['ergebnis' => 'abgelehnt', 'grund' => $grund];
    }

    /**
     * Nummer der Anmeldung aus einem Ereignis. „neu:<Kennung>“ verweist auf eine
     * Nachmeldung, die in derselben Warteschlange vorausging (Gerät ohne Netz).
     */
    private static function anmeldungsId(mixed $ref): int
    {
        $ref = (string) $ref;
        if (str_starts_with($ref, 'neu:')) {
            $id = Str::substr($ref, 4);

            return Str::isUuid($id)
                ? (int) VorOrtEreignis::query()->where('uuid', $id)->where('typ', 'nachmeldung')->where('ergebnis', 'ok')->value('event_registration_id')
                : 0;
        }

        return ctype_digit($ref) ? (int) $ref : 0;
    }

    /**
     * Nachmeldung vor Ort (docs/vor-ort.md): Person ist da, hat sich aber nicht
     * angemeldet. Wird sofort bestätigt (auch über die Platzzahl hinaus), ohne
     * Mail; ein Beitrag wird wie bei Eintragungen im Backend gefordert.
     *
     * @param  array<string, mixed>  $e
     * @param  array<string, mixed>  $daten
     * @return array{ergebnis: string, grund?: string, anmeldung?: int}
     */
    private static function nachmeldung(User $user, string $uuid, array $e, array $daten): array
    {
        $event = Event::query()->find((int) ($e['event'] ?? 0));
        $registration = null;

        $grund = match (true) {
            $event === null => 'Veranstaltung nicht gefunden.',
            ! $user->can('manageParticipants', $event) => 'Keine Berechtigung für diese Veranstaltung.',
            $event->status === 'draft' => 'Die Veranstaltung ist noch nicht veröffentlicht.',
            default => self::nachmeldungAnlegen($user, $event, $daten, $registration),
        };

        if ($event !== null) {
            $erfasst = isset($e['zeit']) ? rescue(fn () => Carbon::parse((string) $e['zeit']), null, false) : null;
            VorOrtEreignis::query()->create([
                'uuid' => $uuid,
                'event_id' => $event->getKey(),
                'event_registration_id' => $registration?->getKey(),
                'user_id' => $user->getKey(),
                'typ' => 'nachmeldung',
                // Keine Personendaten ins Protokoll: sie stehen in der Anmeldung selbst.
                'daten' => ['preis' => $daten['preis'] ?? null],
                'ergebnis' => $grund === null ? 'ok' : 'abgelehnt',
                'erfasst_am' => $erfasst,
            ]);
        }

        if ($grund !== null) {
            return ['ergebnis' => 'abgelehnt', 'grund' => $grund];
        }
        AuditLogger::log('vor_ort.nachmeldung:'.$registration->getKey(), $user, Event::class, $event->getKey(), request());

        return ['ergebnis' => 'ok', 'anmeldung' => $registration->getKey()];
    }

    /** @param  array<string, mixed>  $daten */
    private static function nachmeldungAnlegen(User $user, Event $event, array $daten, ?EventRegistration &$registration): ?string
    {
        $vorname = trim((string) ($daten['first_name'] ?? ''));
        $nachname = trim((string) ($daten['last_name'] ?? ''));
        if ($vorname === '' || $nachname === '' || mb_strlen($vorname) > 255 || mb_strlen($nachname) > 255) {
            return 'Vor- und Nachname dürfen nicht leer sein.';
        }
        if (($daten['einverstanden'] ?? false) !== true) {
            return 'Das Einverständnis zur Anmeldung (bei Minderjährigen der Eltern) muss bestätigt sein.';
        }

        $felder = $event->formFields();
        $data = [];
        $jahr = null;
        foreach ((array) ($daten['data'] ?? []) as $k => $wert) {
            $feld = $felder[$k] ?? null;
            if ($feld === null) {
                return 'Unbekanntes Feld.';
            }
            $fehler = self::feldPruefen($event, $feld, $wert);
            if ($fehler !== null) {
                return $feld['label'].': '.$fehler;
            }
            if ($k === 'birth_year') {
                $jahr = filled($wert) ? (int) $wert : null;
            } elseif (filled($wert)) {
                $data[$k] = $wert;
            }
        }
        foreach ($felder as $k => $feld) {
            $wert = $k === 'birth_year' ? $jahr : ($data[$k] ?? null);
            if (($feld['required'] ?? false) && ! filled($wert) && $feld['type'] !== 'days') {
                return $feld['label'].' ist ein Pflichtfeld.';
            }
        }

        $preis = null;
        if ($event->hatTeilnahmebeitrag()) {
            $preis = $event->preise()[(int) ($daten['preis'] ?? -1)] ?? null;
            if ($preis === null) {
                return 'Bitte eine Preisstufe wählen.';
            }
        }

        [$ergebnis, $neu] = app(EventRegistrations::class)->addManually($event, [
            'first_name' => $vorname, 'last_name' => $nachname, 'birth_year' => $jahr, 'data' => $data, 'preis' => $preis,
        ], 'confirmed', false, $user);
        if ($ergebnis === 'duplicate') {
            return "{$vorname} {$nachname} ist schon angemeldet.";
        }
        // Wie im Backend ohne Online-Einwilligung eingetragen, hier ausdrücklich vor Ort.
        $neu->update(['consent_text_version' => self::NACHMELDUNG]);
        $registration = $neu;

        return null;
    }

    /**
     * Sensible Angaben nicht ins Protokoll (nur, dass sie geändert wurden).
     *
     * @param  array<string, mixed>  $daten
     * @return array<string, mixed>
     */
    private static function protokollDaten(string $typ, array $daten, ?Event $event): array
    {
        // Das Bild nicht doppelt speichern – nur seine Prüfsumme.
        if ($typ === 'unterschrift') {
            $png = is_string($daten['bild'] ?? null) ? base64_decode(substr($daten['bild'], strlen('data:image/png;base64,')), true) : false;

            return ['pruefsumme' => $png === false ? null : hash('sha256', $png)];
        }
        if ($typ !== 'angaben' || $event === null) {
            return $daten;
        }
        $sensibel = $event->sensibleFelder();
        $data = (array) ($daten['data'] ?? []);
        foreach ($sensibel as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = '(sensibel, geändert)';
            }
        }

        return [...$daten, 'data' => $data];
    }

    /** @param  array<string, mixed>  $daten */
    private static function anwesend(EventRegistration $r, array $daten): ?string
    {
        $tag = (string) ($daten['tag'] ?? '');
        $tage = array_keys($r->event->days()) ?: [$r->event->starts_at->toDateString()];
        if (! in_array($tag, $tage, true)) {
            return 'Dieser Tag gehört nicht zur Veranstaltung.';
        }
        $da = collect((array) $r->vor_ort_tage);
        $da = ($daten['da'] ?? true) ? $da->push($tag)->unique() : $da->reject(fn ($t) => $t === $tag);
        $r->update(['vor_ort_tage' => $da->isEmpty() ? null : $da->sort()->values()->all()]);

        return null;
    }

    /** @param  array<string, mixed>  $daten */
    private static function zahlung(User $user, EventRegistration $r, array $daten): ?string
    {
        $cent = (int) ($daten['cent'] ?? 0);
        $weg = self::zahlwege()[(string) ($daten['weg'] ?? '')] ?? null;
        if ($weg === null) {
            return 'Unbekannte Zahlungsart.';
        }
        $zahlung = Features::enabled(Features::ZAHLUNGEN) ? $r->zahlung() : null;
        if ($zahlung === null || ! in_array($zahlung->status, ['offen', 'teilweise'], true)) {
            return 'Es gibt keine offene Forderung.';
        }
        if ($cent <= 0 || $cent > $zahlung->offenCent()) {
            return 'Der Betrag passt nicht zur offenen Forderung.';
        }
        $zahlung->eingang($cent, now(), $user->getKey(), $weg.' vor Ort');

        return null;
    }

    /** @param  array<string, mixed>  $daten */
    private static function unterschrift(User $user, EventRegistration $r, array $daten, mixed $zeit): ?string
    {
        if (! Unterschriften::moeglich($r->event)) {
            return 'Für diese Veranstaltung gibt es keine Unterschriftenliste (kein Zuschussantrag).';
        }
        $fehler = Unterschriften::pruefen($daten['bild'] ?? null);
        if ($fehler !== null) {
            return $fehler;
        }
        $am = is_string($zeit) ? rescue(fn () => Carbon::parse($zeit), null, false) : null;
        Unterschriften::speichern($r, (string) $daten['bild'], $user, $am !== null && $am->lte(now()) ? $am : now());

        return null;
    }

    /** @param  array<string, mixed>  $daten */
    private static function zusatz(EventRegistration $r, array $daten): ?string
    {
        $feld = (string) ($daten['feld'] ?? '');
        if (! array_key_exists($feld, $r->event->zusatzFelder())) {
            return 'Unbekanntes Feld.';
        }
        $wert = trim((string) ($daten['wert'] ?? ''));
        if (mb_strlen($wert) > 100) {
            return 'Höchstens 100 Zeichen.';
        }
        $zusatz = (array) $r->zusatz;
        if ($wert === '') {
            unset($zusatz[$feld]);
        } else {
            $zusatz[$feld] = $wert;
        }
        $r->update(['zusatz' => $zusatz === [] ? null : $zusatz]);

        return null;
    }

    /** @param  array<string, mixed>  $daten */
    private static function angaben(EventRegistration $r, array $daten): ?string
    {
        $felder = $r->event->formFields();
        $neu = [];
        foreach (['first_name', 'last_name'] as $k) {
            if (array_key_exists($k, $daten)) {
                $wert = trim((string) $daten[$k]);
                if ($wert === '' || mb_strlen($wert) > 255) {
                    return 'Vor- und Nachname dürfen nicht leer sein.';
                }
                $neu[$k] = $wert;
            }
        }

        $data = (array) $r->data;
        foreach ((array) ($daten['data'] ?? []) as $k => $wert) {
            $feld = $felder[$k] ?? null;
            if ($feld === null) {
                return 'Unbekanntes Feld.';
            }
            $fehler = self::feldPruefen($r->event, $feld, $wert);
            if ($fehler !== null) {
                return $feld['label'].': '.$fehler;
            }
            if ($k === 'birth_year') {
                $neu['birth_year'] = filled($wert) ? (int) $wert : null;
            } else {
                $data[$k] = $wert;
            }
        }

        $r->update([...$neu, 'data' => $data]);

        return null;
    }

    /** @param  array<string, mixed>  $feld */
    private static function feldPruefen(Event $event, array $feld, mixed $wert): ?string
    {
        if ($wert === null || $wert === '' || $wert === []) {
            return null;
        }

        return match ($feld['type']) {
            'select', 'herkunft' => is_string($wert) && array_key_exists($wert, (array) ($feld['options'] ?? [])) ? null : 'ungültige Auswahl.',
            'mehrfach' => is_array($wert) && array_diff($wert, array_keys((array) ($feld['options'] ?? []))) === [] ? null : 'ungültige Auswahl.',
            'days' => is_array($wert) && array_diff($wert, array_keys($event->days())) === [] ? null : 'ungültige Tage.',
            'year' => is_numeric($wert) && (int) $wert >= 1900 && (int) $wert <= (int) date('Y') ? null : 'ungültiges Jahr.',
            'zahl' => is_numeric($wert) && (int) $wert >= 0 ? null : 'ungültige Zahl.',
            default => is_scalar($wert) && mb_strlen((string) $wert) <= 1000 ? null : 'zu lang.',
        };
    }
}
