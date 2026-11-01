<?php

namespace App\Support\Zuschuesse;

/**
 * Wohnort → Kreis und Gemeinde für Saarland und Rheinland-Pfalz
 * (resources/data/plz-gebiete.php, aus der OpenPLZ API). Grundlage für
 * Zuschussgeber mit Wohnort-Bedingung und für das Gebiet des Bistums Trier.
 * Eine PLZ kann mehrere Gemeinden und Kreise berühren; dann ist die
 * Zuordnung nur sicher, wenn alle im Gebiet liegen bzw. keine.
 */
final class Gebiete
{
    /**
     * Bistum Trier (Wikipedia „Bistum Trier“, Bistumsgebiet): Saarland ohne
     * Saarpfalz-Kreis; in Rheinland-Pfalz die früheren Regierungsbezirke
     * Trier und Koblenz ohne Westerwaldkreis und Rhein-Lahn-Kreis.
     */
    public const BISTUM_TRIER = ['10041', '10042', '10043', '10044', '10046',
        '07211', '07231', '07232', '07233', '07235',
        '07111', '07131', '07133', '07134', '07135', '07137', '07138', '07140'];

    /** Nur teilweise im Bistum Trier: Altenkirchen (nur die „Trierische Insel“ um Kirchen/Sieg). */
    public const BISTUM_TRIER_TEILWEISE = ['07132'];

    /** Früher hessische Orte im Kreis Bad Kreuznach (Verbandsgemeinde Bad Kreuznach), Bistum Mainz. */
    private const BISTUM_MAINZ_IM_KREIS_KH = ['Altenbamberg', 'Biebelsheim', 'Feilbingert', 'Frei-Laubersheim', 'Fürfeld', 'Hackenheim',
        'Hallgarten', 'Hochstätten', 'Neu-Bamberg', 'Pfaffen-Schwabenheim', 'Pleitersheim', 'Tiefenthal', 'Volxheim'];

    /** @var array{kreise: array<string, string>, gemeinden: array<string, string>, plz: array<string, list<string>>}|null */
    private static ?array $daten = null;

    /** @return array{kreise: array<string, string>, gemeinden: array<string, string>, plz: array<string, list<string>>} */
    private static function daten(): array
    {
        return self::$daten ??= require resource_path('data/plz-gebiete.php');
    }

    /** @return array<string, string> Kreisschlüssel => Name */
    public static function kreise(): array
    {
        return self::daten()['kreise'];
    }

    /** @return array<string, string> Gemeindeschlüssel => Name */
    public static function gemeinden(): array
    {
        return self::daten()['gemeinden'];
    }

    /** Auswahl für Formulare: Kreise und Gemeinden mit Kreis, Schlüssel => Bezeichnung. @return array<string, string> */
    public static function auswahl(): array
    {
        $kreise = self::kreise();
        $liste = collect($kreise)->mapWithKeys(fn (string $name, string $key) => [$key => 'Kreis '.$name])->all();
        foreach (self::gemeinden() as $key => $name) {
            $liste[$key] = $name.' ('.($kreise[substr($key, 0, 5)] ?? '?').')';
        }

        return $liste;
    }

    public static function name(string $key): string
    {
        return strlen($key) === 5 ? 'Kreis '.(self::kreise()[$key] ?? $key) : (self::gemeinden()[$key] ?? $key);
    }

    /** @return list<string> Gemeindeschlüssel einer PLZ (leer: unbekannt bzw. außerhalb SL/RLP). */
    public static function gemeindenVonPlz(?string $plz): array
    {
        $plz = preg_replace('/\D/', '', (string) $plz) ?? '';

        return self::daten()['plz'][$plz] ?? [];
    }

    /**
     * Liegt der Wohnort im Gebiet (Liste aus Kreis- und Gemeindeschlüsseln)?
     * null = nicht sicher (unbekannte PLZ oder PLZ teils drinnen, teils draußen).
     *
     * @param  list<string>  $gebiet
     */
    public static function imGebiet(?string $plz, array $gebiet): ?bool
    {
        $gemeinden = self::gemeindenVonPlz($plz);
        if ($gemeinden === []) {
            // Außerhalb von Saarland und RLP: drinnen nur, wenn das Gebiet nichts davon enthält – also nicht.
            return filled($plz) && $gebiet !== [] && ! self::plzInSlRlp($plz) ? false : null;
        }
        $drin = array_map(fn (string $g) => in_array($g, $gebiet, true) || in_array(substr($g, 0, 5), $gebiet, true), $gemeinden);

        return self::eindeutig($drin);
    }

    /** Wohnort im Bistum Trier? null = nicht sicher. */
    public static function bistumTrier(?string $plz): ?bool
    {
        $gemeinden = self::gemeindenVonPlz($plz);
        if ($gemeinden === []) {
            return filled($plz) && ! self::plzInSlRlp($plz) ? false : null;
        }
        $namen = self::gemeinden();
        $drin = array_map(function (string $g) use ($namen) {
            $kreis = substr($g, 0, 5);
            if (in_array($kreis, self::BISTUM_TRIER_TEILWEISE, true)) {
                return null;
            }
            if ($kreis === '07133' && in_array($namen[$g] ?? '', self::BISTUM_MAINZ_IM_KREIS_KH, true)) {
                return false;
            }

            return in_array($kreis, self::BISTUM_TRIER, true);
        }, $gemeinden);

        return self::eindeutig($drin);
    }

    /** @param list<?bool> $werte */
    private static function eindeutig(array $werte): ?bool
    {
        if (in_array(null, $werte, true)) {
            return null;
        }
        $werte = array_unique($werte);

        return count($werte) === 1 ? $werte[0] : null;
    }

    /** Postleitzahlbereiche von Saarland und RLP (grob), um „außerhalb“ von „unbekannt“ zu trennen. */
    private static function plzInSlRlp(string $plz): bool
    {
        $plz = preg_replace('/\D/', '', $plz) ?? '';
        if (strlen($plz) !== 5) {
            return true;
        }

        return in_array(substr($plz, 0, 2), ['54', '55', '56', '66', '67', '76'], true)
            || in_array(substr($plz, 0, 3), ['534', '535', '575', '576'], true);
    }
}
