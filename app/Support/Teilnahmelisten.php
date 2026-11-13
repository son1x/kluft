<?php

namespace App\Support;

use App\Models\Event;
use Closure;
use Illuminate\Support\Collection;

/**
 * Modelle der Teilnahmeliste (docs/vor-ort.md): je Zuschussgeber oder Aktion
 * eigene Spalten. Ein neues Modell ist ein Eintrag in alle(); die Liste als PDF
 * (zum Unterschreiben, mit Kopf zu Veranstalter, Ort und Datum) und, wo es eine
 * Vorlage gibt, als Excel kommen daraus. Zuschussanträge mit eigenem Vordruck
 * (Formular B …) bleiben im Zuschussantrag.
 *
 * Spalte: label, wert (Person, Veranstaltung) => string|int|null, zahl (Excel als Zahl).
 * pflicht: Angaben, die aus der Anmeldung kommen müssen (plz, wohnort, strasse, geburtsdatum, geburtsjahr).
 * excel: Pfad der Excel-Vorlage unter resources/vorlagen oder null.
 * kopf: „einspaltig“ (Veranstalter, Veranstaltung, Ort, Datum untereinander) oder „beginn_ende“
 *       (Beginn/Ende links, Aktion/Ort rechts). ausrichtung: portrait oder landscape.
 * betreuung_markieren: Zeilen der Betreuung farbig und mit Legende.
 */
class Teilnahmelisten
{
    public const STANDARD = 'standard';

    public const LJR_RLP = 'ljr-rlp';

    public const RODEN = 'roden';

    /** Pflichtangabe des Modells => Feld der Anmeldung (Schlüssel in `data`, Geburtsjahr als Spalte). */
    public const FELD_FUER = ['plz' => 'plz', 'wohnort' => 'wohnort', 'strasse' => 'strasse', 'geburtsdatum' => 'geburtsdatum', 'geburtsjahr' => 'birth_year'];

    /** @return array<string, array{name: string, beschreibung: string, spalten: list<array{label: string, wert: Closure, zahl?: bool}>, pflicht: list<string>, excel: ?string, kopf: string, ausrichtung: string, betreuung_markieren: bool}> */
    public static function alle(): array
    {
        return [
            self::STANDARD => [
                'name' => 'Standard (Name, Jahrgang, Wohnort)',
                'beschreibung' => 'Einfache Liste zum Unterschreiben.',
                'spalten' => [
                    ['label' => 'Name', 'wert' => fn (array $p) => $p['nachname'].', '.$p['vorname']],
                    ['label' => 'Geburtsjahr', 'wert' => fn (array $p) => $p['geburtsjahr']],
                    ['label' => 'Wohnort', 'wert' => fn (array $p) => $p['wohnort']],
                ],
                'pflicht' => [],
                'excel' => null,
                'kopf' => 'einspaltig',
                'ausrichtung' => 'portrait',
                'betreuung_markieren' => false,
            ],
            self::LJR_RLP => [
                'name' => 'Landesjugendring Rheinland-Pfalz',
                'beschreibung' => 'Spalten der LJR-Vorlage (Name, Vorname, Wohnort Deutschland/Ausland, PLZ, Ort, Teilgenommen als, Anwesenheitstage, Geburtsjahr). Dazu die Excel-Vorlage des LJR. Im Anmeldeformular PLZ, Wohnort und Geburtsjahr bzw. Geburtsdatum abfragen.',
                'spalten' => [
                    ['label' => 'Name', 'wert' => fn (array $p) => $p['nachname']],
                    ['label' => 'Vorname', 'wert' => fn (array $p) => $p['vorname']],
                    ['label' => 'Wohnort', 'wert' => fn (array $p) => self::land($p['plz'])],
                    ['label' => 'PLZ', 'wert' => fn (array $p) => $p['plz']],
                    ['label' => 'Ort', 'wert' => fn (array $p) => $p['wohnort']],
                    ['label' => 'Teilgenommen als', 'wert' => fn (array $p, Event $e) => self::teilgenommenAls($p['funktion'], $e)],
                    ['label' => 'Anwesenheitstage', 'wert' => fn (array $p) => count($p['tage']), 'zahl' => true],
                    ['label' => 'Geburtsjahr', 'wert' => fn (array $p) => $p['geburtsjahr'], 'zahl' => true],
                ],
                'pflicht' => ['plz', 'wohnort', 'geburtsjahr'],
                'excel' => 'ljr-rlp-teilnahmeliste.xlsx',
                'kopf' => 'einspaltig',
                'ausrichtung' => 'landscape',
                'betreuung_markieren' => false,
            ],
            self::RODEN => [
                'name' => 'Stamm Roden (Querformat, Beginn/Ende, Aktion, Ort)',
                'beschreibung' => 'Vordruck des Stamms Roden: Kopf mit Beginn, Ende, Aktion und Ort, Spalten Vorname, Nachname, Straße, PLZ, Ort, Geburtsdatum, Unterschrift; Betreuung farbig. Im Anmeldeformular Straße, PLZ, Wohnort und Geburtsdatum abfragen.',
                'spalten' => [
                    ['label' => 'Vorname', 'wert' => fn (array $p) => $p['vorname']],
                    ['label' => 'Nachname', 'wert' => fn (array $p) => $p['nachname']],
                    ['label' => 'Straße, Nr.', 'wert' => fn (array $p) => $p['strasse']],
                    ['label' => 'PLZ', 'wert' => fn (array $p) => $p['plz']],
                    ['label' => 'Ort', 'wert' => fn (array $p) => $p['wohnort']],
                    ['label' => 'Geburtsdatum', 'wert' => fn (array $p) => $p['geburtsdatum']?->format('d.m.Y')],
                ],
                'pflicht' => ['strasse', 'plz', 'wohnort', 'geburtsdatum'],
                'excel' => null,
                'kopf' => 'beginn_ende',
                'ausrichtung' => 'landscape',
                'betreuung_markieren' => true,
            ],
        ];
    }

    /** @return array{name: string, beschreibung: string, spalten: list<array{label: string, wert: Closure, zahl?: bool}>, pflicht: list<string>, excel: ?string, kopf: string, ausrichtung: string, betreuung_markieren: bool} */
    public static function modell(?string $key): array
    {
        return self::alle()[$key ?? ''] ?? self::alle()[self::STANDARD];
    }

    /** @return array<string, string> */
    public static function auswahl(): array
    {
        return array_map(fn (array $m) => $m['name'], self::alle());
    }

    /** Deutschland bei fünfstelliger PLZ, sonst Ausland (z. B. Luxemburg, Belgien); ohne PLZ leer. */
    public static function land(?string $plz): string
    {
        $plz = trim((string) $plz);

        return $plz === '' ? '' : (preg_match('/^\d{5}$/', $plz) ? 'Deutschland' : 'Ausland');
    }

    /** LJR: Teilnehmer*in oder Betreuung (Leitung, Referent*in) für Teilnehmende ohne bzw. mit Behinderung. */
    public static function teilgenommenAls(string $funktion, Event $event): string
    {
        if ($funktion === 'TN') {
            return 'Teilnehmer:in';
        }

        return 'Betreuer:in für Teilnehmende '.($event->liste_mit_behinderung ? 'mit' : 'ohne').' Behinderung';
    }

    /**
     * Kopf der Liste als Zeilen mit je einem oder zwei Paaren (Beschriftung, Wert):
     * Veranstalter, Veranstaltung, Veranstaltungsort, Datum – oder Beginn/Ende und Aktion/Ort.
     *
     * @param  array<string, mixed>  $modell
     * @return list<list<array{0: string, 1: string}>>
     */
    public static function kopf(Event $event, array $modell): array
    {
        $veranstalter = (string) (filled($event->veranstalter) ? $event->veranstalter : config('dpsg.site.name'));
        $beginn = $event->starts_at->format('d.m.Y');
        $ende = ($event->ends_at ?? $event->starts_at)->format('d.m.Y');

        $zeilen = $modell['kopf'] === 'beginn_ende'
            ? [
                [['Beginn', $beginn], ['Aktion', (string) $event->title]],
                [['Ende', $ende], ['Ort', (string) $event->location]],
                [['Veranstalter', $veranstalter]],
            ]
            : [
                [['Veranstalter', $veranstalter]],
                [['Veranstaltung', (string) $event->title]],
                [['Veranstaltungsort', (string) $event->location]],
                [['Datum', $event->dateLabel()]],
            ];

        return array_values(array_filter(array_map(fn (array $zeile) => array_values(array_filter($zeile, fn (array $p) => filled($p[1]))), $zeilen)));
    }

    /** Personen der Liste: bestätigt, Betreuung zuerst; Anwesenheitstage aus „Vor Ort“, wenn dort erfasst. */
    public static function personen(Event $event): Collection
    {
        return Zuschuesse::personen($event, true, true);
    }

    /**
     * Zeilen für PDF und Excel.
     *
     * @param  array<string, mixed>  $modell
     * @return list<array{id: int, betreuung: bool, werte: list<string|int|null>}>
     */
    public static function zeilen(Event $event, array $modell): array
    {
        return self::personen($event)->map(fn (array $p) => [
            'id' => $p['id'],
            'betreuung' => $p['funktion'] !== 'TN',
            'werte' => array_map(fn (array $s) => $s['wert']($p, $event), $modell['spalten']),
        ])->all();
    }

    /** Beschriftung der Pflichtangaben für Hinweise. */
    private const NAMEN = ['plz' => 'PLZ', 'wohnort' => 'Wohnort', 'strasse' => 'Straße', 'geburtsdatum' => 'Geburtsdatum', 'geburtsjahr' => 'Geburtsjahr'];

    /**
     * Fehlende Pflichtangaben einer Person als Felder der Anmeldung (Schlüssel wie in `data`; Geburtsjahr = `birth_year`).
     * Ist das Geburtsdatum bekannt, fehlt das Geburtsjahr nicht.
     *
     * @param  array<string, mixed>  $modell
     * @param  array<string, mixed>  $p
     * @return list<string>
     */
    public static function fehlendeFelder(array $modell, array $p): array
    {
        $fehlt = [];
        foreach ($modell['pflicht'] as $k) {
            if (blank($p[$k] ?? null)) {
                $fehlt[] = self::FELD_FUER[$k] ?? $k;
            }
        }

        return $fehlt;
    }

    /**
     * Felder, die das Modell braucht, auch wenn das Anmeldeformular sie nicht abfragt
     * (Vor Ort kann sie nachtragen): Schlüssel => Feld (label, type) wie `Event::formFields()`.
     *
     * @param  array<string, mixed>  $modell
     * @return array<string, array<string, mixed>>
     */
    public static function modellFelder(Event $event, ?array $modell = null): array
    {
        $modell ??= self::modell($event->teilnahmeliste_modell);
        $alle = (array) config('dpsg.event_fields');
        $felder = [];
        foreach ($modell['pflicht'] as $k) {
            $feld = self::FELD_FUER[$k] ?? $k;
            if (isset($alle[$feld]) && ! isset($event->formFields()[$feld])) {
                $felder[$feld] = $alle[$feld] + ['required' => false];
            }
        }

        return $felder;
    }

    /**
     * Personen, bei denen Pflichtangaben des Modells fehlen.
     *
     * @param  array<string, mixed>  $modell
     * @return list<array{name: string, fehlt: list<string>}>
     */
    public static function fehlend(Event $event, array $modell): array
    {
        $fehlend = [];
        foreach (self::personen($event) as $p) {
            $fehlt = array_values(array_map(fn (string $k) => self::NAMEN[$k] ?? $k, array_filter($modell['pflicht'], fn (string $k) => blank($p[$k] ?? null))));
            if ($fehlt !== []) {
                $fehlend[] = ['name' => trim($p['vorname'].' '.$p['nachname']), 'fehlt' => $fehlt];
            }
        }

        return $fehlend;
    }

    public static function dateiname(Event $event, string $endung): string
    {
        return 'Teilnahmeliste-'.str($event->title)->slug().'-'.now()->format('Y-m-d').'.'.$endung;
    }
}
