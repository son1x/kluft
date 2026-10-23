<?php

namespace App\Services\Beitraege;

use App\Models\Beitrag;
use App\Models\BeitragsMitglied;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiSitzungAbgelaufen;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\Beitraege;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Mitglieder des Stamms aus NaMi für die Beitragskasse holen und die
 * Beiträge eines Halbjahres anlegen. Gespeichert wird nur, was die Kasse
 * braucht: Name, Mitgliedsnummer, Beitragsart, Ein- und Austritt. Mitglieder
 * zahlen Beitrag; Schnuppermitglieder stehen markiert und ohne Beitrag in der
 * Liste, Nicht-Mitglieder (z. B. Kontakte) gar nicht.
 * Details werden nur für neue oder in NaMi geänderte Personen geladen.
 */
class BeitragsAbruf
{
    /** Beitragsart in der Liste für Schnuppermitglieder. */
    public const SCHNUPPER = 'Schnuppermitglied';

    /** Meldung der ersten fehlgeschlagenen Detailabfrage im letzten Lauf. */
    public ?string $detailFehler = null;

    public function __construct(private NamiVerzeichnis $nami) {}

    /**
     * Höchstens $maxDetails Detailabfragen je Lauf; wer danach noch fehlt,
     * zählt in „offen“ und kommt beim nächsten Lauf dran. Verweigert NaMi
     * die Details einzelner Personen, zählen sie in „ohne_details“ und werden
     * erst wieder abgefragt, wenn sich ihre NaMi-Version ändert (bis dahin
     * kann die Kasse die Beitragsart von Hand setzen); erst wenn die ersten
     * Abfragen alle scheitern, bricht der Abruf ab.
     *
     * @return array{mitglieder: int, neu: int, details: int, offen: int, ausgetreten: int, ohne_details: int}
     *
     * @throws MitgliederverzeichnisException
     */
    public function abrufen(string $nummer, ?int $maxDetails = null): array
    {
        $gruppierung = $this->nami->gruppierungId($nummer);
        $arten = $this->nami->beitragsarten($gruppierung);
        $ergebnis = ['mitglieder' => 0, 'neu' => 0, 'details' => 0, 'offen' => 0, 'ausgetreten' => 0, 'ohne_details' => 0];
        $gesehen = [];
        $this->detailFehler = null;

        foreach ($this->nami->mitgliederListe($gruppierung) as $zeile) {
            $namiId = (int) ($zeile['id'] ?? 0);
            $typ = mb_strtolower((string) ($zeile['entries_mglType'] ?? ''));
            if ($namiId === 0 || ! str_contains($typ, 'mitglied') || str_contains($typ, 'nicht')) {
                continue;
            }
            $gesehen[] = $namiId;
            $version = is_numeric($zeile['entries_version'] ?? null) ? (int) $zeile['entries_version'] : null;

            $mitglied = BeitragsMitglied::query()->firstOrNew(['nami_id' => $namiId]);
            $mitglied->fill([
                'vorname' => mb_substr((string) ($zeile['entries_vorname'] ?? ''), 0, 100),
                'nachname' => mb_substr((string) ($zeile['entries_nachname'] ?? ''), 0, 100),
                'mitgliedsnummer' => filled($zeile['entries_mitgliedsNummer'] ?? null) ? (string) $zeile['entries_mitgliedsNummer'] : null,
                'aktiv' => mb_strtolower((string) ($zeile['entries_status'] ?? 'aktiv')) === 'aktiv',
                'schnupper' => str_contains($typ, 'schnupper'),
                'abgerufen_am' => now(),
            ]);
            // Ein- und Austritt stehen schon in der Liste (NaMi 2), die Beitragsart nur in den Details.
            if (array_key_exists('entries_eintrittsdatum', $zeile)) {
                $mitglied->fill(['eintritt' => self::datum($zeile['entries_eintrittsdatum']), 'austritt' => self::datum($zeile['entries_austrittsDatum'] ?? null)]);
            }
            // Hat NaMi die Details verweigert, erst bei geänderter Version wieder fragen.
            $brauchtDetails = ! $mitglied->exists || $version === null || $mitglied->nami_version !== $version
                || ($mitglied->beitragsart === null && ! $mitglied->schnupper && ! $mitglied->details_verweigert);
            if ($brauchtDetails && $maxDetails !== null && $ergebnis['details'] >= $maxDetails) {
                // Ohne Details speichern; nami_version bleibt alt, damit der nächste Lauf sie holt.
                $ergebnis['offen']++;
            } elseif ($brauchtDetails && ($details = $this->details($gruppierung, $namiId, $ergebnis)) === null) {
                $mitglied->fill(['details_verweigert' => true, 'nami_version' => $version]);
            } elseif ($brauchtDetails) {
                $art = filled($details['beitragsart'] ?? null) ? Beitraege::artName((string) $details['beitragsart']) : ($arten[(int) ($details['beitragsartId'] ?? 0)] ?? null);
                $mitglied->fill(['nami_version' => $version, 'details_verweigert' => false]);
                // Eine von Hand gesetzte Art bleibt, bis NaMi selbst eine liefert.
                if ($art !== null || ! $mitglied->beitragsart_manuell) {
                    $mitglied->fill(['beitragsart' => $art, 'beitragsart_manuell' => false]);
                }
                if (! array_key_exists('entries_eintrittsdatum', $zeile)) {
                    $mitglied->fill(['eintritt' => self::datum($details['eintrittsdatum'] ?? null), 'austritt' => self::datum($details['austrittsDatum'] ?? null)]);
                }
                $ergebnis['details']++;
            }
            $ergebnis['neu'] += $mitglied->exists ? 0 : 1;
            $mitglied->save();
            $ergebnis['mitglieder']++;
        }

        // Nicht mehr in NaMi gelistet: nicht löschen (Zahlungen bleiben), nur inaktiv.
        $ergebnis['ausgetreten'] = BeitragsMitglied::query()->where('aktiv', true)->whereNotIn('nami_id', $gesehen ?: [0])->update(['aktiv' => false]);

        return $ergebnis;
    }

    /**
     * Beiträge des Halbjahres für alle, die darin Mitglied waren. Neue
     * bekommen den einheitlichen Beitrag des Stamms bzw. das Soll ihrer
     * Beitragsart (Bundesbeitrag plus Stammesanteil); offene Beiträge folgen einer
     * geänderten Beitragsart, bezahlte bleiben unverändert.
     *
     * @return array{neu: int, angepasst: int}
     */
    public function halbjahrAnlegen(string $halbjahr): array
    {
        [$beginn, $ende] = Beitraege::zeitraum($halbjahr);
        $ergebnis = ['neu' => 0, 'angepasst' => 0];

        $mitglieder = BeitragsMitglied::query()
            ->where(fn (Builder $q) => $q->whereNull('eintritt')->orWhereDate('eintritt', '<=', $ende))
            ->where(fn (Builder $q) => $q->where(fn (Builder $a) => $a->where('aktiv', true)->whereNull('austritt'))->orWhereDate('austritt', '>=', $beginn))
            ->get();

        foreach ($mitglieder as $mitglied) {
            $beitrag = Beitrag::query()->firstOrNew(['beitrags_mitglied_id' => $mitglied->id, 'halbjahr' => $halbjahr]);
            // Schnuppermitglieder: in der Liste, aber nichts zu zahlen; nach dem Wechsel zum Mitglied folgt der offene Beitrag dem Satz.
            $soll = $mitglied->schnupper ? ['soll' => 0, 'bund' => 0] : Beitraege::soll($mitglied->beitragsart);
            $werte = ['beitragsart' => $mitglied->schnupper ? self::SCHNUPPER : $mitglied->beitragsart, 'soll_cent' => $soll['soll'], 'bundes_cent' => $soll['bund']];
            if (! $beitrag->exists) {
                $beitrag->fill($werte)->save();
                $ergebnis['neu']++;
            } elseif ($beitrag->bezahlt_am === null && $beitrag->zahlart !== 'erlassen'
                && ($beitrag->beitragsart !== $werte['beitragsart'] || $beitrag->soll_cent !== $werte['soll_cent'] || $beitrag->bundes_cent !== $werte['bundes_cent'])) {
                $beitrag->fill($werte)->save();
                $ergebnis['angepasst']++;
            }
        }

        return $ergebnis;
    }

    /**
     * @param  array{details: int, ohne_details: int}  $ergebnis
     * @return array<string, mixed>|null
     *
     * @throws MitgliederverzeichnisException
     */
    private function details(int $gruppierung, int $namiId, array &$ergebnis): ?array
    {
        try {
            return $this->nami->mitgliedDetails($gruppierung, $namiId);
        } catch (NamiSitzungAbgelaufen $e) {
            throw $e;
        } catch (MitgliederverzeichnisException $e) {
            $this->detailFehler ??= $e->getMessage();
            // Klappt keine Abfrage und gab es noch nie eine Beitragsart, liegt es nicht an einzelnen Personen.
            if (++$ergebnis['ohne_details'] >= 5 && $ergebnis['details'] === 0 && ! BeitragsMitglied::query()->whereNotNull('beitragsart')->exists()) {
                throw $e;
            }

            return null;
        }
    }

    /** Mitglieder (keine Schnuppermitglieder), deren Beitragsart fehlt. */
    public static function ohneBeitragsart(): int
    {
        return BeitragsMitglied::query()->where('aktiv', true)->where('schnupper', false)->whereNull('beitragsart')->count();
    }

    private static function datum(mixed $wert): ?Carbon
    {
        return is_string($wert) && preg_match('/^\d{4}-\d{2}-\d{2}/', $wert) ? Carbon::parse(substr($wert, 0, 10)) : null;
    }
}
