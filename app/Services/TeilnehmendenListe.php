<?php

namespace App\Services;

use App\Models\Event;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Liest eine Teilnehmendenliste für die Sammeleintragung im Backend: aus
 * Excel kopiert (Tabulatoren), als CSV (Semikolon/Komma) oder als Datei
 * (.xlsx, .csv). Eine Kopfzeile ordnet die Spalten zu (Vorname, Nachname,
 * Name, E-Mail, Geburtsjahr sowie alle Felder der Veranstaltung, z. B. PLZ,
 * Wohnort, Stamm); ohne Kopfzeile gilt Vorname, Nachname, E-Mail, Geburtsjahr.
 */
class TeilnehmendenListe
{
    public const STANDARD = ['first_name', 'last_name', 'email', 'birth_year'];

    private const MAX_ZEILEN = 500;

    /**
     * @return array{personen: list<array{zeile: int, first_name: string, last_name: string, email: string, birth_year: int|null, data: array<string, string>}>, fehler: list<string>}
     */
    public function ausText(Event $event, string $text): array
    {
        $zeilen = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $trenner = str_contains($text, "\t") ? "\t" : (substr_count((string) ($zeilen[0] ?? ''), ';') > 0 ? ';' : ',');
        $tabelle = array_map(fn (string $zeile) => str_getcsv($zeile, $trenner, '"', ''), array_filter($zeilen, fn ($z) => trim($z) !== ''));

        return $this->auswerten($event, array_values($tabelle));
    }

    /**
     * @return array{personen: list<array{zeile: int, first_name: string, last_name: string, email: string, birth_year: int|null, data: array<string, string>}>, fehler: list<string>}
     */
    public function ausDatei(Event $event, string $pfad, string $name): array
    {
        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($endung === 'csv' || $endung === 'txt') {
            $inhalt = (string) file_get_contents($pfad);
            if (! mb_check_encoding($inhalt, 'UTF-8')) {
                $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252'); // Excel-CSV unter Windows
            }

            return $this->ausText($event, (string) preg_replace('/^\xEF\xBB\xBF/', '', $inhalt));
        }
        if ($endung !== 'xlsx') {
            return ['personen' => [], 'fehler' => ['Bitte eine .xlsx- oder .csv-Datei hochladen.']];
        }

        $reader = new XlsxReader;
        $reader->open($pfad);
        $tabelle = [];
        foreach ($reader->getSheetIterator() as $blatt) {
            foreach ($blatt->getRowIterator() as $row) {
                $zellen = [];
                foreach ($row->getCells() as $zelle) {
                    $wert = $zelle->getValue();
                    $zellen[] = $wert instanceof \DateTimeInterface ? $wert->format('Y') : (is_scalar($wert) ? (string) $wert : '');
                }
                $tabelle[] = $zellen;
                if (count($tabelle) > self::MAX_ZEILEN + 1) {
                    break 2;
                }
            }
            break; // nur das erste Blatt
        }
        $reader->close();

        return $this->auswerten($event, array_values(array_filter($tabelle, fn (array $r) => implode('', array_map('trim', $r)) !== '')));
    }

    /**
     * @param  list<list<string>>  $tabelle
     * @return array{personen: list<array{zeile: int, first_name: string, last_name: string, email: string, birth_year: int|null, data: array<string, string>}>, fehler: list<string>}
     */
    private function auswerten(Event $event, array $tabelle): array
    {
        if ($tabelle === []) {
            return ['personen' => [], 'fehler' => ['Die Liste ist leer.']];
        }

        $felder = $event->formFields();
        $kopf = $this->kopfzeile($tabelle[0], $felder);
        $start = $kopf === null ? 0 : 1;
        $spalten = $kopf ?? self::STANDARD;

        $personen = [];
        $fehler = [];
        foreach (array_slice($tabelle, $start, self::MAX_ZEILEN) as $i => $zellen) {
            $nr = $i + $start + 1;
            $werte = [];
            foreach ($spalten as $pos => $schluessel) {
                if ($schluessel !== null && isset($zellen[$pos])) {
                    $werte[$schluessel] = trim((string) $zellen[$pos]);
                }
            }
            if (filled($werte['name'] ?? null) && blank($werte['first_name'] ?? null) && blank($werte['last_name'] ?? null)) {
                [$werte['first_name'], $werte['last_name']] = self::nameTeilen($werte['name']);
            }

            $person = [
                'zeile' => $nr,
                'first_name' => mb_substr($werte['first_name'] ?? '', 0, 255),
                'last_name' => mb_substr($werte['last_name'] ?? '', 0, 255),
                'email' => mb_strtolower($werte['email'] ?? ''),
                'birth_year' => null,
                'data' => [],
            ];

            $problem = match (true) {
                $person['first_name'] === '' || $person['last_name'] === '' => 'Vor- oder Nachname fehlt',
                $person['email'] !== '' && filter_var($person['email'], FILTER_VALIDATE_EMAIL) === false => 'E-Mail-Adresse ungültig',
                default => null,
            };
            if (filled($werte['birth_year'] ?? null)) {
                $jahr = (int) $werte['birth_year'];
                if ($jahr >= 1900 && $jahr <= (int) date('Y')) {
                    $person['birth_year'] = $jahr;
                } else {
                    $problem ??= 'Geburtsjahr ungültig';
                }
            }
            foreach ($felder as $key => $feld) {
                if ($key === 'birth_year' || blank($werte[$key] ?? null)) {
                    continue;
                }
                $wert = $werte[$key];
                if ($feld['type'] === 'plz' && ! preg_match('/^\d{4,5}$/', $wert)) {
                    $problem ??= 'Postleitzahl ungültig';
                }
                if ($feld['type'] === 'mitgliedsnummer' && ! preg_match('/^\d{1,10}$/', $wert)) {
                    $problem ??= 'Mitgliedsnummer ungültig';
                }
                if ($feld['type'] === 'herkunft') {
                    $wert = self::herkunft($wert, (array) ($feld['options'] ?? []));
                    if ($wert === '') {
                        $problem ??= 'Angabe zu „Gast“ unklar (leer/nein oder ja)';
                    }
                }
                if ($feld['type'] === 'select') {
                    $wert = (string) (collect($feld['options'] ?? [])->search(fn (string $label, string $k) => mb_strtolower($label) === mb_strtolower($wert) || $k === mb_strtolower($wert)) ?: '');
                    if ($wert === '') {
                        $problem ??= $feld['label'].' unbekannt';
                    }
                }
                $person['data'][$key] = mb_substr($wert, 0, 1000);
            }

            if ($problem !== null) {
                $fehler[] = 'Zeile '.$nr.': '.$problem;

                continue;
            }
            $personen[] = $person;
        }

        if (count($tabelle) - $start > self::MAX_ZEILEN) {
            $fehler[] = 'Nur die ersten '.self::MAX_ZEILEN.' Zeilen wurden gelesen.';
        }

        return ['personen' => $personen, 'fehler' => $fehler];
    }

    /**
     * Erkennt eine Kopfzeile und liefert die Spaltenzuordnung, sonst null.
     *
     * @param  list<string>  $zellen
     * @param  array<string, array<string, mixed>>  $felder
     * @return list<string|null>|null
     */
    private function kopfzeile(array $zellen, array $felder): ?array
    {
        $namen = [
            'vorname' => 'first_name', 'vornamen' => 'first_name', 'nachname' => 'last_name', 'familienname' => 'last_name',
            'name' => 'name', 'e-mail' => 'email', 'email' => 'email', 'mail' => 'email', 'e-mail-adresse' => 'email',
            'geburtsjahr' => 'birth_year', 'jahrgang' => 'birth_year', 'postleitzahl' => 'plz', 'plz' => 'plz',
            'wohnort' => 'wohnort', 'ort' => 'wohnort', 'stamm' => 'stamm', 'telefon' => 'phone', 'anmerkungen' => 'comment',
            'herkunft' => 'herkunft', 'gast' => 'herkunft',
            'mitgliedsnummer' => 'mitgliedsnummer', 'mitglieds-nr.' => 'mitgliedsnummer', 'mitglieds-nr' => 'mitgliedsnummer', 'nami-nummer' => 'mitgliedsnummer',
        ];
        foreach ($felder as $key => $feld) {
            $namen[mb_strtolower((string) $feld['label'])] = $key;
        }

        $zuordnung = array_map(fn (string $z) => $namen[mb_strtolower(trim($z, " \t\"*:"))] ?? null, $zellen);
        $erkannt = array_filter($zuordnung);

        return in_array('first_name', $erkannt, true) || in_array('last_name', $erkannt, true) || in_array('name', $erkannt, true)
            ? $zuordnung : null;
    }

    /**
     * Spalte „Gast“ bzw. „Herkunft“: ja/x/1/gast oder der Antworttext =
     * Gast; nein/0/hier = von hier.
     *
     * @param  array<string, string>  $optionen
     */
    private static function herkunft(string $wert, array $optionen): string
    {
        $w = mb_strtolower(trim($wert));
        foreach ($optionen as $key => $label) {
            if ($w === $key || $w === mb_strtolower($label)) {
                return $key;
            }
        }

        return match (true) {
            in_array($w, ['ja', 'x', '1', 'gast', 'extern'], true) => 'gast',
            in_array($w, ['nein', '0', 'hier', '-'], true) => 'hier',
            default => '',
        };
    }

    /** @return array{0: string, 1: string} „Muster, Mia“ oder „Mia Muster“ */
    private static function nameTeilen(string $name): array
    {
        if (str_contains($name, ',')) {
            [$nach, $vor] = array_map('trim', explode(',', $name, 2));

            return [$vor, $nach];
        }
        $teile = preg_split('/\s+/', trim($name)) ?: [];
        $nach = (string) array_pop($teile);

        return [implode(' ', $teile), $nach];
    }
}
