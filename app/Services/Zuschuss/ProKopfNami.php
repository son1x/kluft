<?php

namespace App\Services\Zuschuss;

use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiSitzungAbgelaufen;
use App\Services\Mitglieder\NamiVerzeichnis;
use Illuminate\Support\Carbon;

/**
 * Pro-Kopf-Zuschuss der Kreisstadt Saarlouis (ZU-19) direkt aus NaMi statt
 * aus einer Export-Datei: Mitgliederliste des Stamms holen, mit denselben
 * Regeln wie beim Datei-Upload filtern (ProKopfListe::ausgelassen) und für
 * die Jugendlichen die Anschrift aus den NaMi-Details nachladen – in
 * Etappen, weil das je Person eine Anfrage ist. Der Zwischenstand ist ein
 * einfaches Array; die Seite hält ihn nur verschlüsselt und kurz im Cache,
 * Kluft speichert nichts davon dauerhaft.
 */
class ProKopfNami
{
    /**
     * Mitgliederliste holen und filtern. Anschriften fehlen danach noch.
     *
     * @return array{gruppierung: int, stichtag: string, offen: list<array{id: int, nachname: string, vorname: string, geburtsdatum: string}>, personen: list<array{nachname: string, vorname: string, strasse: string, plz: string, ort: string, geburtsdatum: string}>, ausgelassen: array{zu_alt: int, kein_mitglied: int, ohne_datum: int}, ohne_anschrift: int, gesamt: int}
     *
     * @throws MitgliederverzeichnisException
     */
    public function beginnen(NamiVerzeichnis $nami, string $nummer, Carbon $stichtag): array
    {
        $gruppierung = $nami->gruppierungId($nummer);
        $offen = [];
        $ausgelassen = ['zu_alt' => 0, 'kein_mitglied' => 0, 'ohne_datum' => 0];

        foreach ($nami->mitgliederListe($gruppierung) as $zeile) {
            $id = (int) ($zeile['id'] ?? 0);
            if ($id === 0 || trim((string) ($zeile['entries_nachname'] ?? '')) === '') {
                continue;
            }
            $geburt = ProKopfListe::datum(trim((string) ($zeile['entries_geburtsDatum'] ?? '')));
            $grund = ProKopfListe::ausgelassen((string) ($zeile['entries_status'] ?? ''), (string) ($zeile['entries_mglType'] ?? ''), $geburt, $stichtag);
            if ($grund !== null) {
                $ausgelassen[$grund]++;

                continue;
            }
            $offen[] = [
                'id' => $id,
                'nachname' => trim((string) $zeile['entries_nachname']),
                'vorname' => trim((string) ($zeile['entries_vorname'] ?? '')),
                'geburtsdatum' => $geburt->toDateString(),
            ];
        }

        return ['gruppierung' => $gruppierung, 'stichtag' => $stichtag->toDateString(), 'offen' => $offen, 'personen' => [],
            'ausgelassen' => $ausgelassen, 'ohne_anschrift' => 0, 'gesamt' => count($offen)];
    }

    /**
     * Für höchstens $max Jugendliche die Anschrift nachladen. Verweigert NaMi
     * einzelne Details, kommt die Person ohne Anschrift auf die Liste (zum
     * Ergänzen von Hand); eine abgelaufene Anmeldung bricht ab.
     *
     * @param  array<string, mixed>  $stand  aus beginnen() bzw. dem letzten weiter()
     * @return array<string, mixed>
     *
     * @throws MitgliederverzeichnisException
     */
    public function weiter(NamiVerzeichnis $nami, array $stand, int $max): array
    {
        $jetzt = array_splice($stand['offen'], 0, max(1, $max));
        foreach ($jetzt as $person) {
            try {
                $details = $nami->mitgliedDetails((int) $stand['gruppierung'], (int) $person['id']);
            } catch (NamiSitzungAbgelaufen $e) {
                throw $e;
            } catch (MitgliederverzeichnisException) {
                $details = [];
            }
            $strasse = trim((string) ($details['strasse'] ?? ''));
            $stand['ohne_anschrift'] += $strasse === '' ? 1 : 0;
            $stand['personen'][] = [
                'nachname' => $person['nachname'],
                'vorname' => $person['vorname'],
                'strasse' => $strasse,
                'plz' => trim((string) ($details['plz'] ?? '')),
                'ort' => trim((string) ($details['ort'] ?? '')),
                'geburtsdatum' => $person['geburtsdatum'],
            ];
        }

        return $stand;
    }

    /** @param  array<string, mixed>  $stand */
    public static function fertig(array $stand): bool
    {
        return $stand['offen'] === [];
    }

    /**
     * Personen für ZuschussPdf::proKopf, sortiert wie beim Datei-Upload.
     *
     * @param  array<string, mixed>  $stand
     * @return list<array{nachname: string, vorname: string, strasse: string, plz: string, ort: string, geburtsdatum: Carbon}>
     */
    public static function personen(array $stand): array
    {
        return ProKopfListe::sortiert(array_values(array_map(fn (array $p) => [
            'nachname' => (string) $p['nachname'],
            'vorname' => (string) $p['vorname'],
            'strasse' => (string) $p['strasse'],
            'plz' => (string) $p['plz'],
            'ort' => (string) $p['ort'],
            'geburtsdatum' => Carbon::parse((string) $p['geburtsdatum']),
        ], (array) $stand['personen'])));
    }
}
