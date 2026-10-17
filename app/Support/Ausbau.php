<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Ausbaustufe einer Website: Ein Stamm startet klein und schaltet mehr frei,
 * wenn er so weit ist. Kluft läuft immer komplett; die Stufe bestimmt nur,
 * was auf der Website und im Backend erscheint. Gespeicherte Inhalte bleiben
 * beim Zurückschalten erhalten.
 *
 * Standard ist config dpsg.ausbau (Stämme aus dem Assistenten; DV und
 * Bezirk ohne Angabe: Vollausbau). Admins ändern sie unter Verwaltung →
 * Ausbaustufe (Tabelle site_settings).
 */
class Ausbau
{
    public const VISITENKARTE = 1;

    public const GRUPPEN = 2;

    public const TERMINE = 3;

    public const ANMELDUNGEN = 4;

    public const VOLL = 5;

    /** @var array<int, array{name: string, text: string}> */
    public const STUFEN = [
        self::VISITENKARTE => ['name' => 'Visitenkarte', 'text' => 'Startseite, Kontakt, Impressum und Datenschutz.'],
        self::GRUPPEN => ['name' => 'Gruppen', 'text' => 'Dazu „Unsere Gruppen“ mit Gruppenstunde und Ort je Gruppe, Stammesvorstand und alle weiteren Seiten (z. B. Heim, Förderverein).'],
        self::TERMINE => ['name' => 'Termine & Aktuelles', 'text' => 'Dazu Kalender, Veranstaltungen (ohne Online-Anmeldung) und Meldungen mit RSS-Feed.'],
        self::ANMELDUNGEN => ['name' => 'Anmeldungen', 'text' => 'Dazu Online-Anmeldung zu Lagern und Aktionen, „Meine Anmeldungen“, NaMi-Abgleich, Teilnahmebescheinigungen.'],
        self::VOLL => ['name' => 'Vollausbau', 'text' => 'Alle Funktionen, z. B. Downloads, Push-Nachrichten und WhatsApp-Kanal.'],
    ];

    private const SCHLUESSEL = 'ausbau';

    private const CACHE_KEY = 'ausbau.stufe';

    public static function stufe(): int
    {
        try {
            $wert = Cache::get(self::CACHE_KEY);
            if (! is_int($wert)) {
                $wert = Schema::hasTable('site_settings')
                    ? (int) SiteSetting::query()->whereKey(self::SCHLUESSEL)->value('value')
                    : 0;
                Cache::forever(self::CACHE_KEY, $wert);
            }
        } catch (Throwable) {
            $wert = 0;
        }

        return self::gueltig($wert) ? $wert : self::standard();
    }

    /** Standard der Instanz (ohne Einstellung im Backend). */
    public static function standard(): int
    {
        $wert = (int) config('dpsg.ausbau', self::VOLL);

        return self::gueltig($wert) ? $wert : self::VOLL;
    }

    public static function reicht(int $mindestens): bool
    {
        return self::stufe() >= $mindestens;
    }

    public static function gruppen(): bool
    {
        return self::reicht(self::GRUPPEN);
    }

    public static function termine(): bool
    {
        return self::reicht(self::TERMINE);
    }

    public static function anmeldungen(): bool
    {
        return self::reicht(self::ANMELDUNGEN);
    }

    /**
     * Seite auf der Website erreichbar? In der Visitenkarte nur Kontakt und
     * die Pflichtseiten, ab „Gruppen“ alle.
     */
    public static function seiteSichtbar(string $slug): bool
    {
        return self::gruppen() || in_array($slug, [config('dpsg.contact_page'), 'impressum', 'datenschutz', 'barrierefreiheit'], true);
    }

    public static function name(?int $stufe = null): string
    {
        return self::STUFEN[$stufe ?? self::stufe()]['name'];
    }

    /** Festlegen; gleich dem Standard entfernt die Einstellung. */
    public static function setzen(int $stufe, ?int $userId = null): void
    {
        if (! self::gueltig($stufe)) {
            throw new \InvalidArgumentException("Ausbaustufe {$stufe} gibt es nicht.");
        }
        if ($stufe === self::standard()) {
            SiteSetting::query()->whereKey(self::SCHLUESSEL)->delete();
        } else {
            SiteSetting::query()->updateOrCreate(['key' => self::SCHLUESSEL], ['value' => (string) $stufe, 'updated_by' => $userId]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    private static function gueltig(int $stufe): bool
    {
        return isset(self::STUFEN[$stufe]);
    }
}
