<?php

namespace App\Services\Mitglieder;

use App\Support\KluftVersion;

/**
 * Testabfrage gegen NaMi 2 für die Entwicklung: Was liefern Anmeldung,
 * Gruppierungsbaum, Mitgliederliste, Mitgliedsdetails und Beitragsarten
 * wirklich? Das Ergebnis ist ein Text zum Kopieren, ohne Personendaten:
 * Werte erscheinen nur als Muster („Aaaa“, „9999-99-99“), Merkmale wie
 * Mitgliedstyp oder Status nur als Anzahlen, und nur bei genügend
 * Personen. Namen von Gruppierungen und Beitragsarten bleiben lesbar.
 */
class NamiDiagnose
{
    /** Felder, die nie als Wert oder Verteilung erscheinen. */
    private const PERSOENLICH = ['name', 'vorname', 'nachname', 'spitzname', 'email', 'mail', 'telefon', 'fax', 'strasse', 'plz', 'ort',
        'geburt', 'konto', 'iban', 'bic', 'bank', 'kontoinhaber', 'nummer', 'descriptor', 'id', 'version', 'datum', 'date'];

    /** Felder, deren Wert im Detail lesbar bleibt (ohne Bezug zu einer Person). */
    private const LESBAR = ['beitragsartId', 'beitragsart', 'mglTypeId', 'mglType', 'status', 'statusId', 'entries_mglType', 'entries_status',
        'staatsangehoerigkeitId', 'geschlechtId', 'konfessionId', 'regionId', 'landId', 'zeitschriftenversand', 'wiederverwendenFlag'];

    /** @var list<string> */
    private array $zeilen = [];

    public function __construct(private NamiVerzeichnis $nami) {}

    public function bericht(string $nummer): string
    {
        $this->zeilen = [];
        $this->zeile('Kluft-NaMi-Testabfrage '.now()->format('Y-m-d H:i').' · Kluft '.(KluftVersion::version() ?? '?').' · '.config('mitglieder.nami.url'));
        $this->zeile('Personendaten sind ersetzt: Buchstaben → a/A, Ziffern → 9. Verteilungen nur ab 10 Personen.');

        try {
            $gruppierung = $this->baum($nummer);
            if ($gruppierung !== null) {
                $mitglieder = $this->liste($gruppierung);
                $this->details($gruppierung, $mitglieder);
                $this->beitragsarten($gruppierung);
            }
        } catch (MitgliederverzeichnisException $e) {
            $this->zeile('ABBRUCH: '.$e->getMessage());
        }

        return implode("\n", $this->zeilen);
    }

    private function baum(string $nummer): ?int
    {
        $this->abschnitt('Gruppierungsbaum');
        $start = $this->nami->rohAbfrage('/gruppierungen/filtered-for-navigation/gruppierung/node/1');
        $this->antwort('node/1', $start);
        foreach (array_slice((array) ($start['body']['data'] ?? []), 0, 12) as $g) {
            $this->zeile('  '.json_encode(['id' => $g['id'] ?? null, 'descriptor' => $g['descriptor'] ?? null], JSON_UNESCAPED_UNICODE));
        }
        $this->zeile('  Felder je Eintrag: '.implode(', ', array_keys((array) ($start['body']['data'][0] ?? []))));

        if (preg_replace('/\D/', '', $nummer) === '') {
            $this->zeile('Keine Gruppierungsnummer angegeben – Liste und Details übersprungen.');

            return null;
        }
        try {
            $id = $this->nami->gruppierungId($nummer);
            $this->zeile("Gruppierung {$nummer} gefunden: NaMi-ID {$id}.");

            return $id;
        } catch (MitgliederverzeichnisException $e) {
            $this->zeile("Gruppierung {$nummer}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function liste(int $gruppierung): array
    {
        $this->abschnitt('Mitgliederliste (flist)');
        $antwort = $this->nami->rohAbfrage('/mitglied/filtered-for-navigation/gruppierung/gruppierung/'.$gruppierung.'/flist', ['page' => 1, 'start' => 0, 'limit' => 5000]);
        $this->antwort('flist', $antwort);
        $zeilen = array_values(array_filter((array) ($antwort['body']['data'] ?? []), 'is_array'));
        $this->zeile('  totalEntries: '.json_encode($antwort['body']['totalEntries'] ?? null).' · Zeilen: '.count($zeilen));
        if ($zeilen === []) {
            return [];
        }
        $this->zeile('  Erste Zeile (nur Form der Werte):');
        foreach ($zeilen[0] as $feld => $wert) {
            $this->zeile('    '.$feld.': '.$this->muster((string) $feld, $wert));
        }
        $this->verteilungen($zeilen);

        $mitglieder = array_values(array_filter($zeilen, fn (array $z) => ($t = mb_strtolower((string) ($z['entries_mglType'] ?? ''))) !== '' && str_contains($t, 'mitglied') && ! str_contains($t, 'nicht') && ! str_contains($t, 'schnupper')));
        $this->zeile('  Kluft würde '.count($mitglieder).' von '.count($zeilen).' als beitragspflichtige Mitglieder übernehmen.');

        return $mitglieder;
    }

    /**
     * @param  list<array<string, mixed>>  $mitglieder
     */
    private function details(int $gruppierung, array $mitglieder): void
    {
        $this->abschnitt('Mitgliedsdetails (zwei Beispiele)');
        foreach (array_slice($mitglieder, 0, 2) as $i => $zeile) {
            $antwort = $this->nami->rohAbfrage('/mitglied/filtered-for-navigation/gruppierung/gruppierung/'.$gruppierung.'/'.(int) ($zeile['id'] ?? 0));
            $this->antwort('Beispiel '.($i + 1), $antwort);
            foreach ((array) ($antwort['body']['data'] ?? []) as $feld => $wert) {
                $this->zeile('    '.$feld.': '.$this->muster((string) $feld, $wert));
            }
        }
    }

    private function beitragsarten(int $gruppierung): void
    {
        $this->abschnitt('Beitragsarten');
        $antwort = $this->nami->rohAbfrage('ica/rest/namiBeitrag/beitragsartmgl/gruppierung/'.$gruppierung);
        $this->antwort('beitragsartmgl', $antwort);
        foreach ((array) ($antwort['body']['data'] ?? []) as $art) {
            $this->zeile('  '.json_encode(is_array($art) ? $art : ['wert' => $art], JSON_UNESCAPED_UNICODE));
        }
        $this->zeile('  Kluft liest daraus: '.json_encode($this->nami->beitragsarten($gruppierung), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Anzahlen je Wert für Merkmale mit wenigen Ausprägungen (z. B. Typ, Status).
     *
     * @param  list<array<string, mixed>>  $zeilen
     */
    private function verteilungen(array $zeilen): void
    {
        if (count($zeilen) < 10) {
            $this->zeile('  Verteilungen: weniger als 10 Personen – nicht ausgegeben.');

            return;
        }
        foreach (array_keys($zeilen[0]) as $feld) {
            if ($this->persoenlich((string) $feld)) {
                continue;
            }
            $werte = array_map(fn (array $z) => is_scalar($z[$feld] ?? null) ? (string) $z[$feld] : '…', $zeilen);
            $anzahl = array_count_values($werte);
            // Werte unter drei Personen nur als Summe, damit niemand erkennbar wird.
            $haeufig = array_filter($anzahl, fn (int $n) => $n >= 3);
            $selten = array_diff_key($anzahl, $haeufig);
            if ($haeufig !== [] && count($haeufig) <= 8 && count($selten) <= 8) {
                arsort($haeufig);
                if ($selten !== []) {
                    $haeufig['(seltene Werte, je unter 3)'] = array_sum($selten);
                }
                $this->zeile('  Verteilung '.$feld.': '.json_encode($haeufig, JSON_UNESCAPED_UNICODE));
            }
        }
    }

    private function muster(string $feld, mixed $wert): string
    {
        if (in_array($feld, self::LESBAR, true) && (is_scalar($wert) || $wert === null)) {
            return json_encode($wert, JSON_UNESCAPED_UNICODE).' (lesbar)';
        }

        return match (true) {
            $wert === null => 'null',
            is_bool($wert) => $wert ? 'true' : 'false',
            is_int($wert) => 'int',
            is_float($wert) => 'float',
            is_array($wert) => 'array('.count($wert).')'.($wert !== [] && ! array_is_list($wert) ? ' {'.implode(', ', array_keys($wert)).'}' : ''),
            default => $this->text((string) $wert),
        };
    }

    private function text(string $wert): string
    {
        if ($wert === '') {
            return '""';
        }
        $muster = (string) preg_replace(['/\p{Lu}/u', '/\p{Ll}/u', '/\d/'], ['A', 'a', '9'], mb_substr($wert, 0, 24));

        return '"'.$muster.(mb_strlen($wert) > 24 ? '…' : '').'" (Text, '.mb_strlen($wert).' Zeichen)';
    }

    private function persoenlich(string $feld): bool
    {
        $f = mb_strtolower($feld);
        foreach (self::PERSOENLICH as $teil) {
            if (str_contains($f, $teil)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{status: int, body: mixed, ms: int}  $antwort
     */
    private function antwort(string $was, array $antwort): void
    {
        $body = $antwort['body'];
        $this->zeile($was.': HTTP '.$antwort['status'].', '.$antwort['ms'].' ms, '.(is_array($body)
            ? 'success='.json_encode($body['success'] ?? null).', Felder: '.implode(', ', array_keys($body))
                .(isset($body['message']) && is_string($body['message']) ? ', message: '.$this->text($body['message']) : '')
            : 'keine JSON-Antwort'));
    }

    private function abschnitt(string $titel): void
    {
        $this->zeilen[] = '';
        $this->zeilen[] = '== '.$titel.' ==';
    }

    private function zeile(string $text): void
    {
        $this->zeilen[] = $text;
    }
}
