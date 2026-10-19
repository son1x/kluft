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

    public const TITELBILD_WECHSEL = 'titelbild_wechsel';

    public const CODE_LOGIN = 'code_login';

    public const MITGLIEDSBEITRAEGE = 'mitgliedsbeitraege';

    public const NACHRICHTEN = 'nachrichten';

    public const MAILFORMULAR = 'mailformular';

    private const CACHE_KEY = 'features.schalter';

    /**
     * Alle schaltbaren Funktionen: Bereich, Bezeichnung, Beschreibung, Standard
     * (wenn die Instanz nichts festlegt), ggf. Voraussetzung und die nötige
     * Ausbaustufe („ab“, App\Support\Ausbau; ohne Angabe: immer).
     *
     * „ebene“: nur auf Websites dieser Ebene (config dpsg.site.level), sonst
     * gar nicht angeboten.
     *
     * @var array<string, array{ab?: int, ebene?: string, bereich: string, label: string, text: string, standard: bool, braucht?: string}>
     */
    public const KATALOG = [
        self::DOWNLOADS => ['ab' => 5, 'bereich' => 'Website', 'label' => 'Download-Seite', 'text' => 'Seite „Downloads“ mit Methoden, Formularen und Arbeitshilfen; Backend „Downloads“.', 'standard' => false],
        self::GEORGSBOTE => ['ab' => 5, 'bereich' => 'Website', 'label' => 'Zeitschriften-Archiv', 'text' => 'Archiv der Verbandszeitschrift als PDF (beim DV: Georgsbote).', 'standard' => false],
        self::RECHTSTRAEGER => ['ab' => 5, 'bereich' => 'Website', 'label' => 'Schaubild Rechtsträger', 'text' => 'Schaubild auf der Seite „Rechtsträger“.', 'standard' => false],
        self::TITELBILD_WECHSEL => ['bereich' => 'Website', 'label' => 'Titelbild wechselt zufällig', 'text' => 'Die Startseite zeigt bei jedem Aufruf eines der Titelbilder: das der Seite „start“ und die der Instanz. Aus: immer das der Seite „start“, sonst das erste der Instanz.', 'standard' => true],
        self::ROADMAP => ['ab' => 5, 'bereich' => 'Website', 'label' => 'Roadmap', 'text' => 'Öffentliche Seite /roadmap mit dem Projektstand.', 'standard' => false],
        self::TEILNEHMENDE_EINTRAGEN => ['ab' => 4, 'bereich' => 'Veranstaltungen', 'label' => 'Teilnehmende von Hand eintragen', 'text' => 'Einzeln oder als Liste (Excel, CSV) unter „Teilnehmende“.', 'standard' => true],
        self::GAESTE => ['ab' => 4, 'bereich' => 'Veranstaltungen', 'label' => 'Gäste aus anderen Verbänden', 'text' => 'Ankreuzfeld „Gast aus einem anderen Diözesanverband oder Verband“ unter „Stamm / Bezirk / Verband“.', 'standard' => true],
        self::TEILNAHMEBESCHEINIGUNG => ['ab' => 4, 'bereich' => 'Veranstaltungen', 'label' => 'Teilnahmebescheinigung', 'text' => 'PDF mit Prüfcode, Freigabe durch die Veranstaltungsleitung. Die Echtheitsprüfung ausgestellter Bescheinigungen bleibt immer erreichbar.', 'standard' => true],
        self::MITGLIEDERABGLEICH => ['ab' => 4, 'bereich' => 'Mitgliederabgleich (NaMi)', 'label' => 'Mitgliederabgleich', 'text' => 'Teilnehmende mit NaMi abgleichen („Mitgliedschaft prüfen“).', 'standard' => true, 'braucht' => 'MITGLIEDER_DRIVER (Secret)'],
        self::NAMI_PROFIL => ['ab' => 4, 'bereich' => 'Mitgliederabgleich (NaMi)', 'label' => 'NaMi-Zugang im Profil speichern', 'text' => '„Mein Konto → Mein NaMi-Zugang“: Zugang verschlüsselt speichern, Ampel, Verbindungstest.', 'standard' => true, 'braucht' => 'Mitgliederabgleich'],
        self::NUR_MITGLIEDER => ['ab' => 4, 'bereich' => 'Mitgliederabgleich (NaMi)', 'label' => 'Veranstaltungen nur für Mitglieder einer Gruppierung', 'text' => 'Einstellung „Nur für Mitglieder von …“ (Stamm/Bezirk), Markierung eigene/andere Gruppierung.', 'standard' => true],
        self::NACHRICHTEN => ['bereich' => 'Kontakt', 'label' => 'Nachrichten-Eingang', 'text' => 'Nachrichten aus dem Kontaktformular und Fragen zu Veranstaltungen zusätzlich im Backend speichern („Verband & Kontakt → Nachrichten“, für Verwaltung und Admins), mit Status und Notiz. Die zuständige Person bekommt sie weiter per Mail. Gelöscht wird nach einer festen Frist (Standard 6 Monate). Vor dem Einschalten die Datenschutzerklärung anpassen: Nachrichten werden dann gespeichert.', 'standard' => false],
        self::MAILFORMULAR => ['bereich' => 'Kontakt', 'label' => 'E-Mail-Adressen öffnen ein Formular', 'text' => 'Ein Klick auf eine E-Mail-Adresse der Website (Personen, Kontakt, eigene Domain im Seitentext) öffnet ein Formular an genau diese Adresse statt des Mailprogramms; die Adresse bleibt daneben sichtbar. Die Nachricht geht per Mail an die Person und liegt mit dem Nachrichten-Eingang zusätzlich im Backend.', 'standard' => false, 'braucht' => 'Nachrichten-Eingang (für die Kopie)'],
        self::MITGLIEDSBEITRAEGE => ['ebene' => 'Stamm', 'bereich' => 'Kasse', 'label' => 'Mitgliedsbeiträge', 'text' => 'Kasse → Mitgliedsbeiträge: Mitglieder je Halbjahr aus NaMi, offene und bezahlte Beiträge, Abgleich mit der Rechnung des Bundesverbands. Sichtbar nur mit der Rolle „Kasse“ (oder Admin).', 'standard' => false, 'braucht' => 'MITGLIEDER_DRIVER=nami'],
        self::PUSH => ['ab' => 5, 'bereich' => 'Backend', 'label' => 'Push-Nachrichten (ntfy)', 'text' => '„Mein Konto → Meine Push-Nachrichten“ und Versand aufs Handy.', 'standard' => true],
        self::CODE_LOGIN => ['bereich' => 'Backend', 'label' => 'Anmeldung per E-Mail-Code', 'text' => 'Redaktion ohne Microsoft 365: Zugänge für Personen oder Rollen (Funktionspostfach, z. B. „Stammesvorstand“), Anmeldung mit Einmalcode per E-Mail. Microsoft 365 bleibt daneben möglich.', 'standard' => false],
        self::WP_IMPORT => ['ab' => 3, 'bereich' => 'Backend', 'label' => 'WordPress-Import', 'text' => 'Übernahme von Seiten und Beiträgen aus einer WordPress-Website.', 'standard' => false],
    ];

    public static function enabled(string $feature): bool
    {
        if (! self::verfuegbar($feature) || ! Ausbau::reicht(self::KATALOG[$feature]['ab'] ?? Ausbau::VISITENKARTE)) {
            return false;
        }

        return self::geschaltet($feature);
    }

    /** Gibt es die Funktion auf dieser Website überhaupt (Ebene, z. B. nur für Stämme)? */
    public static function verfuegbar(string $feature): bool
    {
        $ebene = self::KATALOG[$feature]['ebene'] ?? null;

        return $ebene === null || $ebene === config('dpsg.site.level');
    }

    /**
     * Die auf dieser Website angebotenen Funktionen.
     *
     * @return array<string, array{ab?: int, ebene?: string, bereich: string, label: string, text: string, standard: bool, braucht?: string}>
     */
    public static function katalog(): array
    {
        return array_filter(self::KATALOG, fn (string $key) => self::verfuegbar($key), ARRAY_FILTER_USE_KEY);
    }

    /** Schalter bzw. Standard, ohne Ausbaustufe (für Verwaltung → Funktionen). */
    public static function geschaltet(string $feature): bool
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
