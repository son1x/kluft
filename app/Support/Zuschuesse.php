<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\Zuschuesse\Antrag;
use App\Support\Zuschuesse\Kreise;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Zuschussanträge für Veranstaltungen (docs/pflichtenheft-zuschuesse.md):
 * einheitliche Personenliste aus den bestätigten Anmeldungen, Zählung je
 * Kreis und Funktion, fehlende Angaben.
 */
class Zuschuesse
{
    /** Anmeldefelder, die bei „Zuschuss beantragen“ Pflicht sind. */
    public const FELDER = ['plz', 'wohnort', 'strasse', 'geburtsdatum', 'geschlecht'];

    public const EINWILLIGUNG = 'Für den Zuschussantrag beim Jugendamt, Landesjugendamt und weiteren Zuschussgebern verarbeiten wir zusätzlich Anschrift, Geburtsdatum und Geschlecht; sie stehen auf der Teilnahmeliste, die du bei der Veranstaltung unterschreibst.';

    public const ARTEN = [
        'freizeit' => 'Freizeit (Lager, Fahrt)',
        'bildung' => 'Bildungsmaßnahme',
        'schulung' => 'Schulung von Ehrenamtlichen',
        'sonstige' => 'sonstige Maßnahme',
    ];

    // Zuschussgeber: App\Support\Zuschuesse\Geber (Katalog je Instanz).

    /** Funktion auf der Teilnahmeliste (Vordruck B: TN, B, R, L). */
    public const FUNKTIONEN = [
        'TN' => 'Teilnehmer*in',
        'B' => 'Betreuer*in',
        'R' => 'Referent*in',
        'L' => 'Leiter*in',
    ];

    public const GESCHLECHTER = ['w' => 'weiblich', 'm' => 'männlich', 'd' => 'divers'];

    public static function aktiv(): bool
    {
        return Features::enabled(Features::ZUSCHUESSE);
    }

    public static function istBetreuung(string $funktion): bool
    {
        return in_array($funktion, ['B', 'L'], true);
    }

    /**
     * Bestätigte und anwesende Personen in einheitlicher Form, Betreuung zuerst.
     *
     * @return Collection<int, array{id: int, vorname: string, nachname: string, strasse: ?string, plz: ?string, wohnort: ?string, geburtsdatum: ?Carbon, alter: ?int, geschlecht: ?string, funktion: string, kreis: string, juleica: bool, tage: list<string>}>
     */
    public static function personen(Event $event, bool $nurAnwesende = true): Collection
    {
        $alleTage = array_keys($event->days()) ?: [$event->starts_at->toDateString()];

        return $event->registrations()->where('status', 'confirmed')->get()
            ->when($nurAnwesende, fn (Collection $c) => $c->filter(fn (EventRegistration $r) => $r->anwesend !== false))
            ->map(function (EventRegistration $r) use ($event, $alleTage) {
                $data = (array) $r->data;
                $geburt = filled($data['geburtsdatum'] ?? null) ? Carbon::parse((string) $data['geburtsdatum']) : null;
                // Anwesenheit je Tag: von Hand erfasst, sonst aus der Anmeldung, sonst alle Tage.
                $tage = $r->anwesend_tage ?: (is_array($data['tage'] ?? null) && $data['tage'] !== [] ? $data['tage'] : $alleTage);

                return [
                    'id' => $r->id,
                    'vorname' => (string) $r->first_name,
                    'nachname' => (string) $r->last_name,
                    'strasse' => $data['strasse'] ?? null,
                    'plz' => $data['plz'] ?? null,
                    'wohnort' => $data['wohnort'] ?? null,
                    'geburtsdatum' => $geburt,
                    // Alter zu Beginn der Veranstaltung; ohne Datum aus dem Geburtsjahr (ungefähr).
                    'alter' => $geburt ? (int) $geburt->diffInYears($event->starts_at) : ($r->birth_year ? $event->starts_at->year - $r->birth_year : null),
                    'geschlecht' => $data['geschlecht'] ?? null,
                    'funktion' => $r->funktion ?: 'TN',
                    'kreis' => $r->kreis ?: Kreise::ausPlz($data['plz'] ?? null),
                    'juleica' => (bool) $r->juleica,
                    'tage' => array_values(array_intersect($alleTage, (array) $tage)),
                ];
            })
            ->sortBy(fn (array $p) => (self::istBetreuung($p['funktion']) ? '0' : ($p['funktion'] === 'R' ? '1' : '2')).mb_strtolower($p['nachname'].' '.$p['vorname']))
            ->values();
    }

    /**
     * Zählung für Formular A und B: je Kreis und Gruppe sowie je Geschlecht.
     *
     * @return array{kreise: array<string, array{tn: int, betreuung: int, referenten: int}>, geschlecht: array{tn: array<string, int>, betreuung: array<string, int>}, gesamt: array{tn: int, betreuung: int, referenten: int}}
     */
    public static function zaehlung(Event $event): array
    {
        $leer = ['tn' => 0, 'betreuung' => 0, 'referenten' => 0];
        $kreise = array_fill_keys(array_keys(Kreise::NAMEN), $leer);
        $geschlecht = ['tn' => ['w' => 0, 'm' => 0, 'd' => 0, '?' => 0], 'betreuung' => ['w' => 0, 'm' => 0, 'd' => 0, '?' => 0]];
        $gesamt = $leer;
        foreach (self::personen($event) as $p) {
            $gruppe = match (true) {
                self::istBetreuung($p['funktion']) => 'betreuung',
                $p['funktion'] === 'R' => 'referenten',
                default => 'tn',
            };
            $kreise[$p['kreis']][$gruppe]++;
            $gesamt[$gruppe]++;
            if ($gruppe !== 'referenten') {
                $geschlecht[$gruppe][$p['geschlecht'] ?? '?'] = ($geschlecht[$gruppe][$p['geschlecht'] ?? '?'] ?? 0) + 1;
            }
        }

        return ['kreise' => $kreise, 'geschlecht' => $geschlecht, 'gesamt' => $gesamt];
    }

    /**
     * Fehlende Angaben je Person (für die Übersicht vor dem Druck).
     *
     * @return list<array{name: string, fehlt: list<string>}>
     */
    public static function fehlend(Event $event): array
    {
        $fehlend = [];
        foreach (self::personen($event) as $p) {
            $fehlt = array_keys(array_filter([
                'Straße' => blank($p['strasse']),
                'PLZ' => blank($p['plz']),
                'Wohnort' => blank($p['wohnort']),
                'Geburtsdatum' => $p['geburtsdatum'] === null,
                'Geschlecht' => blank($p['geschlecht']),
            ]));
            if ($fehlt !== []) {
                $fehlend[] = ['name' => trim($p['vorname'].' '.$p['nachname']), 'fehlt' => $fehlt];
            }
        }

        return $fehlend;
    }

    /**
     * Antragsteller für alle Anträge aus der Maske „Zuschussantrag“
     * (Veranstaltung, sonst Standard des Stamms, sonst Website und Konto).
     *
     * @return array<string, ?string>
     */
    public static function antragsteller(Event $event): array
    {
        return Antrag::werte($event);
    }

    /**
     * Programmpunkte aus dem Textfeld: je Zeile „Zeit: Thema – Referent*in“.
     *
     * @return list<array{zeit: string, thema: string, referent: string}>
     */
    public static function programm(Event $event): array
    {
        $zeilen = preg_split('/\R/', (string) (((array) $event->zuschuss_bericht)['programm'] ?? '')) ?: [];

        return array_values(array_filter(array_map(function (string $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '') {
                return null;
            }
            [$zeit, $rest] = str_contains($zeile, ': ') ? explode(': ', $zeile, 2) : ['', $zeile];
            [$thema, $referent] = str_contains($rest, ' – ') ? explode(' – ', $rest, 2) : [$rest, ''];

            return ['zeit' => trim($zeit), 'thema' => trim($thema), 'referent' => trim($referent)];
        }, $zeilen)));
    }

    /** Juleica gilt, wenn vorhanden und (falls angegeben) am Beginn der Veranstaltung noch gültig. */
    public static function juleicaGueltig(array $daten, Event $event): bool
    {
        return (bool) ($daten['juleica'] ?? false)
            && (blank($daten['juleica_bis'] ?? null) || Carbon::parse((string) $daten['juleica_bis'])->gte($event->starts_at->copy()->startOfDay()));
    }

    /** Qualifikation für Formular D/E und Bistum. */
    public static function qualifikation(array $person): string
    {
        return $person['juleica'] ? 'Juleica-Inhaber*in' : 'langjährige Tätigkeit in der Jugendarbeit';
    }

    /** Aufenthaltsbestätigung (Formular C) nur bei Teilnehmenden aus NK, MZG oder dem Saarpfalz-Kreis. */
    public static function brauchtAufenthaltsbestaetigung(Event $event): bool
    {
        return in_array((string) $event->zuschuss_art, ['freizeit'], true)
            && self::personen($event)->contains(fn (array $p) => in_array($p['kreis'], ['NK', 'MZG', 'HOM'], true));
    }

    /** @return array<string, int> Finanzen in Cent mit Summen (Formular A). */
    public static function finanzen(Event $event): array
    {
        $f = array_map('intval', (array) $event->zuschuss_finanzen);
        $ausgaben = array_sum(array_intersect_key($f, array_flip(['fahrt', 'unterkunft', 'verpflegung', 'raummiete', 'material', 'durchfuehrung', 'honorare'])));
        $einnahmen = ($f['teilnahmebeitraege'] ?? 0) + ($f['spenden'] ?? 0) + ($f['sonstige'] ?? 0);

        return $f + ['ausgaben' => $ausgaben, 'einnahmen' => $einnahmen, 'ungedeckt' => max(0, $ausgaben - $einnahmen)];
    }
}
