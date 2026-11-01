<?php

namespace App\Support;

use App\Models\DatenabgleichZeile;
use App\Models\Datenmeldung;
use App\Models\EventRegistration;
use App\Models\SiteSetting;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiSitzungAbgelaufen;
use App\Services\Mitglieder\NamiVerzeichnis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * NaMi-Datenabgleich (docs/pflichtenheft-nami-datenabgleich.md): Angaben
 * aus „Meine Daten aktualisieren“ und aus Anmeldungen mit NaMi vergleichen.
 * Kluft schreibt nichts in NaMi.
 */
class Datenabgleich
{
    public const STAND = 'datenabgleich_stand';

    /** Verglichene Felder: Schlüssel der Meldung => Bezeichnung. */
    public const FELDER = [
        'geburtsdatum' => 'Geburtsdatum',
        'geschlecht' => 'Geschlecht',
        'strasse' => 'Straße',
        'plz' => 'PLZ',
        'ort' => 'Ort',
        'telefon' => 'Telefon',
        'email' => 'E-Mail',
        'email_eltern' => 'E-Mail Erziehungsberechtigte',
    ];

    public static function aktiv(): bool
    {
        return Features::enabled(Features::NAMI_DATENABGLEICH);
    }

    /** @return array{status: string, text: string, am: ?string} */
    public static function stand(): array
    {
        $daten = json_decode((string) SiteSetting::query()->whereKey(self::STAND)->value('value'), true);

        return is_array($daten) ? $daten + ['status' => '', 'text' => '', 'am' => null] : ['status' => '', 'text' => '', 'am' => null];
    }

    public static function standSetzen(string $status, string $text): void
    {
        SiteSetting::query()->updateOrCreate(['key' => self::STAND], ['value' => (string) json_encode(['status' => $status, 'text' => mb_substr($text, 0, 180), 'am' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Alle zu prüfenden Angaben: bestätigte Meldungen und Anmeldungen der
     * letzten 12 Monate mit Anschrift oder Geburtsdatum; je Person die neueste.
     *
     * @return Collection<int, array{quelle: string, quelle_id: int, datum: Carbon, vorname: string, nachname: string, werte: array<string, ?string>}>
     */
    public static function quellen(): Collection
    {
        $meldungen = Datenmeldung::query()->whereNotNull('bestaetigt_am')->get()->map(fn (Datenmeldung $m) => [
            'quelle' => 'meldung', 'quelle_id' => $m->id, 'datum' => $m->bestaetigt_am,
            'vorname' => (string) ($m->daten['vorname'] ?? ''), 'nachname' => (string) ($m->daten['nachname'] ?? ''),
            'werte' => array_intersect_key((array) $m->daten, self::FELDER),
        ]);
        $anmeldungen = EventRegistration::query()->where('status', 'confirmed')->where('created_at', '>=', now()->subMonths(12))->get()
            ->map(function (EventRegistration $r) {
                $d = (array) $r->data;
                if (blank($d['strasse'] ?? null) && blank($d['geburtsdatum'] ?? null)) {
                    return null;
                }

                return [
                    'quelle' => 'anmeldung', 'quelle_id' => $r->id, 'datum' => $r->confirmed_at ?? $r->created_at,
                    'vorname' => (string) $r->first_name, 'nachname' => (string) $r->last_name,
                    'werte' => ['geburtsdatum' => $d['geburtsdatum'] ?? null, 'geschlecht' => $d['geschlecht'] ?? null, 'strasse' => $d['strasse'] ?? null,
                        'plz' => $d['plz'] ?? null, 'ort' => $d['wohnort'] ?? null, 'telefon' => $d['phone'] ?? null,
                        // Bei Anmeldungen durch Eltern ist die Adresse die der Eltern.
                        'email' => $r->guardian_name ? null : ($r->email ?: null), 'email_eltern' => $r->guardian_name ? ($r->email ?: null) : null],
                ];
            })->filter();

        return $meldungen->concat($anmeldungen)
            ->sortByDesc(fn (array $q) => $q['datum']?->timestamp ?? 0)
            ->unique(fn (array $q) => MitgliederAbgleich::norm($q['vorname']).'|'.MitgliederAbgleich::norm($q['nachname']).'|'.($q['werte']['geburtsdatum'] ?? ''))
            ->values();
    }

    /**
     * Ein Durchlauf: höchstens $max Quellen ab $ab; beim ersten Durchlauf
     * wird die alte Liste ersetzt. Liefert, wie viele noch offen sind.
     *
     * @throws MitgliederverzeichnisException
     */
    public static function abgleichen(NamiVerzeichnis $nami, string $nummer, int $ab = 0, int $max = 30): int
    {
        $gruppierung = $nami->gruppierungId($nummer);
        $liste = collect($nami->mitgliederListe($gruppierung))
            ->filter(fn (array $z) => (int) ($z['id'] ?? 0) > 0 && ! str_contains(mb_strtolower((string) ($z['entries_mglType'] ?? '')), 'nicht'));
        $quellen = self::quellen();
        if ($ab === 0) {
            DatenabgleichZeile::query()->delete();
        }

        foreach ($quellen->slice($ab, $max) as $q) {
            $treffer = self::suchen($liste, $q);
            if ($treffer->count() !== 1) {
                self::zeile($q, $treffer->isEmpty() ? 'nicht_gefunden' : 'unklar', null, []);

                continue;
            }
            $mitglied = $treffer->first();
            try {
                $details = $nami->mitgliedDetails($gruppierung, (int) $mitglied['id']);
            } catch (NamiSitzungAbgelaufen $e) {
                throw $e;
            } catch (MitgliederverzeichnisException) {
                $details = [];
            }
            $felder = self::vergleichen($q['werte'], $details);
            self::zeile($q, $felder === [] ? 'stimmt' : 'abweichung', (string) ($mitglied['entries_mitgliedsNummer'] ?? $details['mitgliedsNummer'] ?? ''), $felder);
        }

        return max(0, $quellen->count() - $ab - $max);
    }

    /** @param array<string, mixed> $q */
    private static function zeile(array $q, string $status, ?string $nummer, array $felder): void
    {
        DatenabgleichZeile::query()->create([
            'quelle' => $q['quelle'], 'quelle_id' => $q['quelle_id'], 'status' => $status,
            'ergebnis' => ['name' => trim($q['vorname'].' '.$q['nachname']), 'datum' => $q['datum']?->toDateString(), 'nummer' => $nummer,
                'geburtsdatum' => $q['werte']['geburtsdatum'] ?? null, 'felder' => $felder],
        ]);
    }

    /**
     * Treffer in der NaMi-Liste: Nachname und erster Vorname; bei mehreren
     * entscheidet das Geburtsdatum.
     *
     * @param  Collection<int, array<string, mixed>>  $liste
     * @param  array<string, mixed>  $q
     * @return Collection<int, array<string, mixed>>
     */
    private static function suchen(Collection $liste, array $q): Collection
    {
        $vorname = MitgliederAbgleich::norm((string) (preg_split('/[\s-]+/u', trim($q['vorname']))[0] ?? ''));
        $nachname = MitgliederAbgleich::norm($q['nachname']);
        $treffer = $liste->filter(fn (array $z) => MitgliederAbgleich::norm((string) ($z['entries_nachname'] ?? '')) === $nachname
            && MitgliederAbgleich::norm((string) (preg_split('/[\s-]+/u', trim((string) ($z['entries_vorname'] ?? '')))[0] ?? '')) === $vorname)->values();
        $geburt = self::datum($q['werte']['geburtsdatum'] ?? null);
        if ($treffer->count() > 1 && $geburt) {
            $treffer = $treffer->filter(fn (array $z) => self::datum($z['entries_geburtsDatum'] ?? null) === $geburt)->values();
        }

        return $treffer;
    }

    /**
     * Abweichende Felder; leere Angaben der Meldung zählen nicht.
     *
     * @param  array<string, mixed>  $werte
     * @param  array<string, mixed>  $nami
     * @return list<array{feld: string, meldung: string, nami: string}>
     */
    public static function vergleichen(array $werte, array $nami): array
    {
        $namiWerte = [
            'geburtsdatum' => self::datum($nami['geburtsDatum'] ?? null),
            'geschlecht' => self::geschlecht($nami),
            'strasse' => $nami['strasse'] ?? null,
            'plz' => $nami['plz'] ?? null,
            'ort' => $nami['ort'] ?? null,
            'telefon' => collect(['telefon1', 'telefon2', 'telefon3'])->map(fn ($k) => $nami[$k] ?? null)->filter()->implode(' / ') ?: null,
            'email' => $nami['email'] ?? null,
            'email_eltern' => $nami['emailVertretungsberechtigter'] ?? null,
        ];
        $felder = [];
        foreach (self::FELDER as $feld => $label) {
            $meldung = trim((string) ($werte[$feld] ?? ''));
            if ($meldung === '') {
                continue;
            }
            $vorhanden = trim((string) ($namiWerte[$feld] ?? ''));
            $gleich = match ($feld) {
                'geburtsdatum' => self::datum($meldung) === $vorhanden,
                'geschlecht' => $vorhanden === '' ? false : $meldung === $vorhanden,
                // Eine der Telefonnummern in NaMi reicht.
                'telefon' => collect(['telefon1', 'telefon2', 'telefon3'])->contains(fn ($k) => filled($nami[$k] ?? null) && self::telefon((string) $nami[$k]) === self::telefon($meldung)),
                default => self::norm($feld, $meldung) === self::norm($feld, $vorhanden),
            };
            if (! $gleich) {
                $felder[] = [
                    'feld' => $label,
                    'meldung' => $feld === 'geschlecht' ? (Zuschuesse::GESCHLECHTER[$meldung] ?? $meldung) : ($feld === 'geburtsdatum' ? self::anzeigeDatum($meldung) : $meldung),
                    'nami' => $vorhanden === '' ? 'fehlt' : ($feld === 'geschlecht' ? (Zuschuesse::GESCHLECHTER[$vorhanden] ?? $vorhanden) : ($feld === 'geburtsdatum' ? self::anzeigeDatum($vorhanden) : $vorhanden)),
                ];
            }
        }

        return $felder;
    }

    /** Vergleichsform: klein, ohne Leerzeichen und Satzzeichen; Straße/Str., ß/ss. */
    public static function norm(string $feld, string $wert): string
    {
        $w = mb_strtolower(trim($wert));
        $w = strtr($w, ['ß' => 'ss', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue']);
        if ($feld === 'strasse') {
            $w = (string) preg_replace('/str(\.|asse|\b)/u', 'str', $w);
        }

        return (string) preg_replace('/[^a-z0-9@]+/u', '', $w);
    }

    public static function telefon(string $wert): string
    {
        $ziffern = (string) preg_replace('/\D+/', '', $wert);

        return str_starts_with($ziffern, '0049') ? '0'.substr($ziffern, 4) : (str_starts_with($ziffern, '49') && strlen($ziffern) > 10 ? '0'.substr($ziffern, 2) : $ziffern);
    }

    private static function datum(mixed $wert): ?string
    {
        if (blank($wert)) {
            return null;
        }
        try {
            return Carbon::parse((string) $wert)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private static function anzeigeDatum(string $wert): string
    {
        return ($d = self::datum($wert)) ? Carbon::parse($d)->format('d.m.Y') : $wert;
    }

    /** @param array<string, mixed> $nami */
    private static function geschlecht(array $nami): ?string
    {
        $text = mb_strtolower((string) ($nami['geschlecht'] ?? ''));

        return match (true) {
            str_contains($text, 'weib') => 'w',
            str_contains($text, 'männ') || str_contains($text, 'maenn') => 'm',
            str_contains($text, 'divers') => 'd',
            ($nami['geschlechtId'] ?? null) == 20 => 'w',
            ($nami['geschlechtId'] ?? null) == 19 => 'm',
            default => null,
        };
    }

    /**
     * Löschfristen (ND-08): unbestätigte Meldungen nach 48 Stunden,
     * bestätigte nach 6 Monaten, Ergebnisse 60 Tage nach dem Abgleich.
     *
     * @return array{meldungen: int, zeilen: int}
     */
    public static function aufraeumen(): array
    {
        $meldungen = Datenmeldung::query()->whereNull('bestaetigt_am')->where('created_at', '<', now()->subHours(48))->delete()
            + Datenmeldung::query()->whereNotNull('bestaetigt_am')->where('bestaetigt_am', '<', now()->subMonths(6))->delete();
        $zeilen = DatenabgleichZeile::query()->where('created_at', '<', now()->subDays(60))->delete();

        return ['meldungen' => $meldungen, 'zeilen' => $zeilen];
    }
}
