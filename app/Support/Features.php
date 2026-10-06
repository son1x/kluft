<?php

namespace App\Support;

use App\Models\FeatureSwitch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Funktionen, die je Kluft-Instanz an- oder abgeschaltet sind. Den Standard
 * legt die Instanz fest (config dpsg.features); Admins können ihn im Backend
 * unter „Verwaltung → Funktionen“ überstimmen (Tabelle feature_switches).
 * Abgeschaltet heißt: keine Routen, keine Menüpunkte, keine Backend-Bereiche
 * bzw. Aktionen. Bereits gespeicherte Daten bleiben erhalten.
 */
class Features
{
    public const GEORGSBOTE = 'georgsbote';

    public const ROADMAP = 'roadmap';

    public const RECHTSTRAEGER = 'rechtstraeger';

    public const WP_IMPORT = 'wp_import';

    public const DOWNLOADS = 'downloads';

    public const MITGLIEDERABGLEICH = 'mitgliederabgleich';

    public const NAMI_PROFIL = 'nami_profil';

    public const NUR_MITGLIEDER = 'nur_mitglieder';

    public const GAESTE = 'gaeste';

    public const TEILNEHMENDE_EINTRAGEN = 'teilnehmende_eintragen';

    public const TEILNAHMEBESCHEINIGUNG = 'teilnahmebescheinigung';

    public const PUSH = 'push';

    private const CACHE_KEY = 'features.schalter';

    /**
     * Alle schaltbaren Funktionen: Bereich, Bezeichnung, Beschreibung, Standard
     * (wenn die Instanz nichts festlegt) und ggf. Voraussetzung.
     *
     * @var array<string, array{bereich: string, label: string, text: string, standard: bool, braucht?: string}>
     */
    public const KATALOG = [
        self::DOWNLOADS => ['bereich' => 'Website', 'label' => 'Download-Seite', 'text' => 'Seite „Downloads“ mit Methoden, Formularen und Arbeitshilfen; Backend „Downloads“.', 'standard' => false],
        self::GEORGSBOTE => ['bereich' => 'Website', 'label' => 'Zeitschriften-Archiv', 'text' => 'Archiv der Verbandszeitschrift als PDF (beim DV: Georgsbote).', 'standard' => false],
        self::RECHTSTRAEGER => ['bereich' => 'Website', 'label' => 'Schaubild Rechtsträger', 'text' => 'Schaubild auf der Seite „Rechtsträger“.', 'standard' => false],
        self::ROADMAP => ['bereich' => 'Website', 'label' => 'Roadmap', 'text' => 'Öffentliche Seite /roadmap mit dem Projektstand.', 'standard' => false],
        self::TEILNEHMENDE_EINTRAGEN => ['bereich' => 'Veranstaltungen', 'label' => 'Teilnehmende von Hand eintragen', 'text' => 'Einzeln oder als Liste (Excel, CSV) unter „Teilnehmende“.', 'standard' => true],
        self::GAESTE => ['bereich' => 'Veranstaltungen', 'label' => 'Gäste aus anderen Verbänden', 'text' => 'Ankreuzfeld „Gast aus einem anderen Diözesanverband oder Verband“ unter „Stamm / Bezirk / Verband“.', 'standard' => true],
        self::TEILNAHMEBESCHEINIGUNG => ['bereich' => 'Veranstaltungen', 'label' => 'Teilnahmebescheinigung', 'text' => 'PDF mit Prüfcode, Freigabe durch die Veranstaltungsleitung. Die Echtheitsprüfung ausgestellter Bescheinigungen bleibt immer erreichbar.', 'standard' => true],
        self::MITGLIEDERABGLEICH => ['bereich' => 'Mitgliederabgleich (NaMi)', 'label' => 'Mitgliederabgleich', 'text' => 'Teilnehmende mit NaMi abgleichen („Mitgliedschaft prüfen“).', 'standard' => true, 'braucht' => 'MITGLIEDER_DRIVER (Secret)'],
        self::NAMI_PROFIL => ['bereich' => 'Mitgliederabgleich (NaMi)', 'label' => 'NaMi-Zugang im Profil speichern', 'text' => '„Mein Konto → Mein NaMi-Zugang“: Zugang verschlüsselt speichern, Ampel, Verbindungstest.', 'standard' => true, 'braucht' => 'Mitgliederabgleich'],
        self::NUR_MITGLIEDER => ['bereich' => 'Mitgliederabgleich (NaMi)', 'label' => 'Veranstaltungen nur für Mitglieder einer Gruppierung', 'text' => 'Einstellung „Nur für Mitglieder von …“ (Stamm/Bezirk), Markierung eigene/andere Gruppierung.', 'standard' => true],
        self::PUSH => ['bereich' => 'Backend', 'label' => 'Push-Nachrichten (ntfy)', 'text' => '„Mein Konto → Meine Push-Nachrichten“ und Versand aufs Handy.', 'standard' => true],
        self::WP_IMPORT => ['bereich' => 'Backend', 'label' => 'WordPress-Import', 'text' => 'Übernahme von Seiten und Beiträgen aus einer WordPress-Website.', 'standard' => false],
    ];

    public static function enabled(string $feature): bool
    {
        $schalter = self::schalter();
        if (array_key_exists($feature, $schalter)) {
            return $schalter[$feature];
        }

        return self::standard($feature);
    }

    /** Standard der Instanz (ohne Schalter im Backend). */
    public static function standard(string $feature): bool
    {
        return (bool) config('dpsg.features.'.$feature, self::KATALOG[$feature]['standard'] ?? false);
    }

    /**
     * Schalter aus der Datenbank (zwischengespeichert). Fehlt die Tabelle
     * (Installation, Tests vor der Migration), gilt der Standard.
     *
     * @return array<string, bool>
     */
    public static function schalter(): array
    {
        try {
            $schalter = Cache::get(self::CACHE_KEY);
            if (is_array($schalter)) {
                return $schalter;
            }
            if (! Schema::hasTable('feature_switches')) {
                return [];
            }
            $schalter = FeatureSwitch::query()->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all();
            Cache::forever(self::CACHE_KEY, $schalter);

            return $schalter;
        } catch (Throwable) {
            return [];
        }
    }

    public static function setzen(string $feature, ?bool $enabled, ?int $userId = null): void
    {
        if ($enabled === null) {
            FeatureSwitch::query()->whereKey($feature)->delete();
        } else {
            FeatureSwitch::query()->updateOrCreate(['key' => $feature], ['enabled' => $enabled, 'updated_by' => $userId]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /** E-Mail-Newsletter über Brevo (beim DV „Georgsbrief“). */
    public static function newsletter(): bool
    {
        return filled(config('dpsg.newsletter.slug'));
    }

    /** Fachbereiche mit eigener Landingpage (z. B. /bolivien). */
    public static function sections(): bool
    {
        return ! empty(config('dpsg.sections'));
    }
}
