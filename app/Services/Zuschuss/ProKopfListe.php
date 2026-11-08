<?php

namespace App\Services\Zuschuss;

use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Pro-Kopf-Zuschuss der Kreisstadt Saarlouis (Richtlinien §§ 12–15,
 * docs/pflichtenheft-zuschuesse.md ZU-19): namentliche Liste der
 * jugendlichen Mitglieder (bis zum vollendeten 18. Lebensjahr am 01.01.)
 * mit Anschrift und Geburtsdatum. Quelle ist der Mitglieder-Export aus
 * NaMi (.xlsx oder .csv); Kluft liest ihn nur für das PDF und speichert
 * nichts davon.
 */
class ProKopfListe
{
    private const MAX_ZEILEN = 2000;

    /** Richtwert der Stadt je Jugendlichem (§ 1 Abs. 2 a). */
    public const PAUSCHALE_CENT = 450;

    /**
     * @return array{personen: list<array{nachname: string, vorname: string, strasse: string, plz: string, ort: string, geburtsdatum: Carbon}>, ausgelassen: array{zu_alt: int, kein_mitglied: int, ohne_datum: int}, fehler: list<string>}
     */
    public function lesen(string $pfad, string $name, Carbon $stichtag): array
    {
        $tabelle = $this->tabelle($pfad, $name);
        if ($tabelle === []) {
            return ['personen' => [], 'ausgelassen' => ['zu_alt' => 0, 'kein_mitglied' => 0, 'ohne_datum' => 0], 'fehler' => ['Die Datei ist leer oder kein .xlsx/.csv.']];
        }

        // Kopfzeile suchen: die erste Zeile mit Nachname und Geburtsdatum.
        $spalten = null;
        foreach (array_slice($tabelle, 0, 10, true) as $nr => $zeile) {
            $zuordnung = array_map(fn (string $z) => self::spalte($z), $zeile);
            if (in_array('nachname', $zuordnung, true) && in_array('geburtsdatum', $zuordnung, true)) {
                $spalten = $zuordnung;
                $tabelle = array_slice($tabelle, $nr + 1);
                break;
            }
        }
        if ($spalten === null) {
            return ['personen' => [], 'ausgelassen' => ['zu_alt' => 0, 'kein_mitglied' => 0, 'ohne_datum' => 0], 'fehler' => ['Keine Kopfzeile mit „Nachname“ und „Geburtsdatum“ gefunden. Bitte den Mitglieder-Export aus NaMi mit diesen Spalten verwenden.']];
        }

        $personen = [];
        $ausgelassen = ['zu_alt' => 0, 'kein_mitglied' => 0, 'ohne_datum' => 0];
        foreach ($tabelle as $zeile) {
            $w = [];
            foreach ($spalten as $i => $key) {
                if ($key !== null && ! isset($w[$key])) {
                    $w[$key] = trim((string) ($zeile[$i] ?? ''));
                }
            }
            if (($w['nachname'] ?? '') === '') {
                continue;
            }
            $geburt = self::datum($w['geburtsdatum'] ?? '');
            if (($grund = self::ausgelassen($w['status'] ?? '', $w['typ'] ?? '', $geburt, $stichtag)) !== null) {
                $ausgelassen[$grund]++;

                continue;
            }
            $personen[] = [
                'nachname' => $w['nachname'],
                'vorname' => $w['vorname'] ?? '',
                'strasse' => trim(($w['strasse'] ?? '').' '.($w['hausnummer'] ?? '')),
                'plz' => $w['plz'] ?? '',
                'ort' => $w['ort'] ?? '',
                'geburtsdatum' => $geburt,
            ];
        }

        return ['personen' => self::sortiert($personen), 'ausgelassen' => $ausgelassen, 'fehler' => []];
    }

    /**
     * Warum jemand nicht auf die Liste kommt, oder null (aufnehmen): nur
     * aktive Mitglieder ohne Schnupper- und Nicht-Mitglieder, mit
     * Geburtsdatum, am Stichtag noch nicht 18. Gilt für Datei und NaMi-Abruf.
     *
     * @return 'kein_mitglied'|'ohne_datum'|'zu_alt'|null
     */
    public static function ausgelassen(string $status, string $typ, ?Carbon $geburt, Carbon $stichtag): ?string
    {
        $status = mb_strtolower($status);
        $typ = mb_strtolower($typ);

        return match (true) {
            ($status !== '' && ! str_contains($status, 'aktiv')) || str_contains($status, 'inaktiv') || str_contains($typ, 'nicht') || str_contains($typ, 'schnupper') => 'kein_mitglied',
            $geburt === null => 'ohne_datum',
            // Jugendlich: am Stichtag noch nicht 18.
            $geburt->copy()->addYears(18)->lte($stichtag) => 'zu_alt',
            default => null,
        };
    }

    /**
     * Nach Nachname und Vorname.
     *
     * @template T of array{nachname: string, vorname: string}
     *
     * @param  list<T>  $personen
     * @return list<T>
     */
    public static function sortiert(array $personen): array
    {
        usort($personen, fn (array $a, array $b) => strcoll(mb_strtolower($a['nachname'].' '.$a['vorname']), mb_strtolower($b['nachname'].' '.$b['vorname'])));

        return $personen;
    }

    private static function spalte(string $kopf): ?string
    {
        $k = mb_strtolower(trim($kopf, " \t\"*:"));

        return match (true) {
            in_array($k, ['nachname', 'familienname', 'name'], true) => 'nachname',
            in_array($k, ['vorname', 'vornamen'], true) => 'vorname',
            in_array($k, ['straße', 'strasse', 'str.', 'anschrift'], true) => 'strasse',
            in_array($k, ['hausnummer', 'nr.'], true) => 'hausnummer',
            in_array($k, ['plz', 'postleitzahl'], true) => 'plz',
            in_array($k, ['ort', 'wohnort', 'stadt'], true) => 'ort',
            in_array($k, ['geburtsdatum', 'geb.-datum', 'geb.datum', 'geburtstag', 'geboren'], true) => 'geburtsdatum',
            in_array($k, ['status', 'mitgliedsstatus', 'mitgliedstatus'], true) => 'status',
            in_array($k, ['mitgliedstyp', 'mitgliedtyp', 'mitgliedsart', 'typ'], true) => 'typ',
            default => null,
        };
    }

    public static function datum(string $wert): ?Carbon
    {
        foreach (['Y-m-d', 'd.m.Y', 'd.m.y', 'Y-m-d H:i:s'] as $format) {
            try {
                $d = Carbon::createFromFormat('!'.$format, $wert);
                if ($d !== false && $d->format($format) === $wert) {
                    return $d;
                }
            } catch (Throwable) {
                // nächstes Format
            }
        }

        return null;
    }

    /** @return list<list<string>> */
    private function tabelle(string $pfad, string $name): array
    {
        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($endung === 'csv' || $endung === 'txt') {
            $inhalt = (string) file_get_contents($pfad);
            if (! mb_check_encoding($inhalt, 'UTF-8')) {
                $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
            }
            $inhalt = (string) preg_replace('/^\xEF\xBB\xBF/', '', $inhalt);
            $zeilen = array_slice(array_filter(preg_split('/\r\n|\r|\n/', $inhalt) ?: [], fn ($z) => trim($z) !== ''), 0, self::MAX_ZEILEN);
            $trenner = str_contains((string) ($zeilen[0] ?? ''), "\t") ? "\t" : (substr_count((string) ($zeilen[0] ?? ''), ';') > 0 ? ';' : ',');

            return array_values(array_map(fn (string $z) => str_getcsv($z, $trenner, '"', ''), $zeilen));
        }
        if ($endung !== 'xlsx') {
            return [];
        }
        $reader = new XlsxReader;
        $reader->open($pfad);
        $tabelle = [];
        foreach ($reader->getSheetIterator() as $blatt) {
            foreach ($blatt->getRowIterator() as $row) {
                $tabelle[] = array_map(function ($zelle) {
                    $wert = $zelle->getValue();

                    return $wert instanceof \DateTimeInterface ? $wert->format('Y-m-d') : (is_scalar($wert) ? (string) $wert : '');
                }, $row->getCells());
                if (count($tabelle) >= self::MAX_ZEILEN) {
                    break 2;
                }
            }
            break;
        }
        $reader->close();

        return $tabelle;
    }
}
