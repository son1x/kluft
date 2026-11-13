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
 * pflicht: Angaben, die aus der Anmeldung kommen müssen (plz, wohnort, geburtsjahr).
 * excel: Pfad der Excel-Vorlage unter resources/vorlagen oder null.
 */
class Teilnahmelisten
{
    public const STANDARD = 'standard';

    public const LJR_RLP = 'ljr-rlp';

    /** @return array<string, array{name: string, beschreibung: string, spalten: list<array{label: string, wert: Closure, zahl?: bool}>, pflicht: list<string>, excel: ?string}> */
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
            ],
        ];
    }

    /** @return array{name: string, beschreibung: string, spalten: list<array{label: string, wert: Closure, zahl?: bool}>, pflicht: list<string>, excel: ?string} */
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
     * Kopf der Liste: Veranstalter, Veranstaltungsort, Datum.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function kopf(Event $event): array
    {
        return array_values(array_filter([
            ['Veranstalter', (string) (filled($event->veranstalter) ? $event->veranstalter : config('dpsg.site.name'))],
            ['Veranstaltung', (string) $event->title],
            ['Veranstaltungsort', (string) $event->location],
            ['Datum', $event->dateLabel()],
        ], fn (array $z) => filled($z[1])));
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
     * @return list<array{id: int, werte: list<string|int|null>}>
     */
    public static function zeilen(Event $event, array $modell): array
    {
        return self::personen($event)->map(fn (array $p) => [
            'id' => $p['id'],
            'werte' => array_map(fn (array $s) => $s['wert']($p, $event), $modell['spalten']),
        ])->all();
    }

    /**
     * Personen, bei denen Pflichtangaben des Modells fehlen.
     *
     * @param  array<string, mixed>  $modell
     * @return list<array{name: string, fehlt: list<string>}>
     */
    public static function fehlend(Event $event, array $modell): array
    {
        $namen = ['plz' => 'PLZ', 'wohnort' => 'Wohnort', 'geburtsjahr' => 'Geburtsjahr'];
        $fehlend = [];
        foreach (self::personen($event) as $p) {
            $fehlt = array_values(array_map(fn (string $k) => $namen[$k] ?? $k, array_filter($modell['pflicht'], fn (string $k) => blank($p[$k] ?? null))));
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
