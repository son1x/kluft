<?php

namespace App\Support;

use App\Models\Media;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Fußzeile der Website, im Backend anpassbar (Verwaltung → Fußzeile).
 * Gespeichert wird nur, was vom Standard der Instanz abweicht
 * (site_settings, Schlüssel „fusszeile“); ohne Eintrag gilt die
 * Konfiguration (dpsg.contact, dpsg.site.parent/links, Newsletter).
 *
 * Die Kontaktangaben gelten nur für die Fußzeile. Impressum, Mails und
 * Kontaktseite nutzen weiter die Angaben der Instanz.
 */
class Fusszeile
{
    public const KEY = 'fusszeile';

    private const CACHE = 'fusszeile:v1';

    /** Was in der dritten Spalte stehen kann. */
    public const SPALTE3 = [
        'standard' => 'Standard (Newsletter, sonst Zelte)',
        'newsletter' => 'Newsletter-Kasten',
        'zelte' => 'Zelte auf der Wiese',
        'bild' => 'Eigenes Bild aus der Mediathek',
        'leer' => 'Nichts (nur „DPSG im Netz“)',
    ];

    /** Erlaubte Link-Adressen: http(s) oder ein Pfad dieser Website (kein „//host“). */
    public const LINK = '#^(https?://|/(?!/))#i';

    /** Kontaktfelder, die sich überschreiben lassen. */
    public const KONTAKT = ['name', 'office', 'street', 'city', 'phone', 'email'];

    /**
     * Gespeicherte Abweichungen, so wie das Backend-Formular sie braucht.
     *
     * @return array{kontakt: array<string, ?string>, service: list<array{label: string, url: string}>, netz: ?list<array{label: string, url: string}>, spalte3: string, bild_media_id: ?int, bild_link: ?string}
     */
    public static function gespeichert(): array
    {
        $roh = Cache::rememberForever(self::CACHE, fn () => (string) SiteSetting::query()->whereKey(self::KEY)->value('value'));
        $daten = json_decode($roh, true);
        $daten = is_array($daten) ? $daten : [];

        return [
            'kontakt' => array_map(fn ($wert) => filled($wert) ? (string) $wert : null, array_intersect_key((array) ($daten['kontakt'] ?? []), array_flip(self::KONTAKT))),
            'service' => self::links($daten['service'] ?? []),
            'netz' => isset($daten['netz']) && is_array($daten['netz']) ? self::links($daten['netz']) : null,
            'spalte3' => array_key_exists((string) ($daten['spalte3'] ?? ''), self::SPALTE3) ? (string) $daten['spalte3'] : 'standard',
            'bild_media_id' => isset($daten['bild_media_id']) ? (int) $daten['bild_media_id'] : null,
            'bild_link' => self::erlaubt($daten['bild_link'] ?? null) ? (string) $daten['bild_link'] : null,
        ];
    }

    /**
     * Speichern; leere Kontaktfelder fallen auf die Instanz zurück, „netz“ = null
     * heißt Standard.
     *
     * @param  array<string, mixed>  $daten
     */
    public static function speichern(array $daten, ?int $userId = null): void
    {
        $kontakt = array_filter(array_map(fn ($wert) => filled($wert) ? trim((string) $wert) : null, array_intersect_key((array) ($daten['kontakt'] ?? []), array_flip(self::KONTAKT))));
        $wert = array_filter([
            'kontakt' => $kontakt ?: null,
            'service' => self::links($daten['service'] ?? []) ?: null,
            'netz' => isset($daten['netz']) && is_array($daten['netz']) ? self::links($daten['netz']) : null,
            'spalte3' => ($daten['spalte3'] ?? 'standard') !== 'standard' ? (string) $daten['spalte3'] : null,
            'bild_media_id' => filled($daten['bild_media_id'] ?? null) ? (int) $daten['bild_media_id'] : null,
            'bild_link' => self::erlaubt(trim((string) ($daten['bild_link'] ?? ''))) ? trim((string) $daten['bild_link']) : null,
        ], fn ($v) => $v !== null);

        $wert === []
            ? SiteSetting::query()->whereKey(self::KEY)->delete()
            : SiteSetting::query()->updateOrCreate(['key' => self::KEY], ['value' => (string) json_encode($wert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_by' => $userId]);
        Cache::forget(self::CACHE);
    }

    public static function zuruecksetzen(): void
    {
        SiteSetting::query()->whereKey(self::KEY)->delete();
        Cache::forget(self::CACHE);
    }

    /**
     * Standard der Instanz für „DPSG im Netz“.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function netzStandard(): array
    {
        return self::links([
            ['label' => 'dpsg.de – Bundesebene', 'url' => 'https://www.dpsg.de'],
            ...array_filter([config('dpsg.site.parent'), ...(array) config('dpsg.site.links', [])]),
        ]);
    }

    /**
     * Alles, was das Layout braucht.
     *
     * @return array{kontakt: array<string, mixed>, service: list<array{label: string, url: string}>, netz: list<array{label: string, url: string}>, spalte3: string, bild: ?Media, bild_link: ?string}
     */
    public static function anzeigen(): array
    {
        $g = self::gespeichert();
        $kontakt = [...(array) config('dpsg.contact'), ...array_filter($g['kontakt'])];
        if (filled($g['kontakt']['phone'] ?? null)) {
            $kontakt['phone_href'] = '+'.ltrim((string) preg_replace('/\D/', '', preg_replace('/^0/', '49', (string) $g['kontakt']['phone'])), '+');
        }

        $spalte3 = match ($g['spalte3']) {
            'standard' => Features::newsletter() ? 'newsletter' : 'zelte',
            'newsletter' => Features::newsletter() ? 'newsletter' : 'zelte',
            default => $g['spalte3'],
        };
        $bild = $spalte3 === 'bild' && $g['bild_media_id'] ? Media::query()->find($g['bild_media_id']) : null;
        if ($spalte3 === 'bild' && ! $bild) {
            $spalte3 = 'leer';
        }

        return [
            'kontakt' => $kontakt,
            'service' => $g['service'],
            'netz' => $g['netz'] ?? self::netzStandard(),
            'spalte3' => $spalte3,
            'bild' => $bild,
            'bild_link' => $g['bild_link'],
        ];
    }

    /**
     * Nur Links mit Bezeichnung und http(s)- bzw. Website-Pfad („/kontakt“), höchstens zehn.
     *
     * @return list<array{label: string, url: string}>
     */
    private static function links(mixed $liste): array
    {
        return collect(is_array($liste) ? $liste : [])
            ->filter(fn ($l) => is_array($l) && filled($l['label'] ?? null) && self::erlaubt($l['url'] ?? null))
            ->map(fn (array $l) => ['label' => trim((string) $l['label']), 'url' => trim((string) $l['url'])])
            ->take(10)
            ->values()
            ->all();
    }

    private static function erlaubt(mixed $url): bool
    {
        return is_string($url) && preg_match(self::LINK, trim($url)) === 1;
    }
}
