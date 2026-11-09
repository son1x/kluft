<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Wartungsmodus von Hand (Verwaltung → Wartungsmodus, `php artisan
 * kluft:wartung`, Actions → Server-Prüfung), z. B. für den Umzug.
 * Besucher*innen sehen die Wartungsseite (503), Angemeldete die Website mit
 * rotem Band; Anmeldung, Backend und Systemstatus bleiben erreichbar.
 * Optional kommen nur Admins ins Backend (Redaktionsstopp).
 *
 * Anders als `php artisan down` (Deploy, Update) liegt der Schalter in der
 * Datenbank: Er wandert beim Kopieren der Datenbank mit und lässt sich im
 * Backend wieder ausschalten.
 */
class Wartung
{
    public const KEY = 'wartung';

    private const CACHE = 'wartung:v1';

    public const TEXT = 'Wir arbeiten gerade an der Website. Bitte schau etwas später noch einmal vorbei.';

    /**
     * @return array{aktiv: bool, text: string, bis: ?Carbon, nur_admins: bool, seit: ?Carbon}
     */
    public static function stand(): array
    {
        try {
            $roh = Cache::rememberForever(self::CACHE, fn () => (string) SiteSetting::query()->whereKey(self::KEY)->value('value'));
        } catch (\Throwable) {
            // Ohne Datenbank (z. B. vor der Einrichtung) gibt es keinen Wartungsmodus von Hand.
            $roh = '';
        }
        $d = json_decode($roh, true);
        $d = is_array($d) ? $d : [];
        $zeit = fn (mixed $w) => is_string($w) && $w !== '' ? rescue(fn () => Carbon::parse($w), null, false) : null;

        return [
            'aktiv' => (bool) ($d['aktiv'] ?? false),
            'text' => filled($d['text'] ?? null) ? (string) $d['text'] : self::TEXT,
            'bis' => $zeit($d['bis'] ?? null),
            'nur_admins' => (bool) ($d['nur_admins'] ?? false),
            'seit' => $zeit($d['seit'] ?? null),
        ];
    }

    public static function aktiv(): bool
    {
        return self::stand()['aktiv'];
    }

    /**
     * Einschalten (oder Text, Ende und Backend-Sperre ändern).
     */
    public static function an(?string $text = null, ?Carbon $bis = null, bool $nurAdmins = false, ?int $userId = null): void
    {
        $alt = self::stand();
        self::speichern([
            'aktiv' => true,
            'text' => filled($text) ? trim((string) $text) : null,
            'bis' => $bis?->toIso8601String(),
            'nur_admins' => $nurAdmins,
            'seit' => ($alt['aktiv'] && $alt['seit'] ? $alt['seit'] : now())->toIso8601String(),
        ], $userId);
    }

    /** Ausschalten; Text und Ende bleiben für das nächste Mal stehen. */
    public static function aus(?int $userId = null): void
    {
        $alt = self::stand();
        self::speichern([
            'aktiv' => false,
            'text' => $alt['text'] !== self::TEXT ? $alt['text'] : null,
            'nur_admins' => $alt['nur_admins'],
        ], $userId);
    }

    /** @param  array<string, mixed>  $wert */
    private static function speichern(array $wert, ?int $userId): void
    {
        SiteSetting::query()->updateOrCreate(['key' => self::KEY], [
            'value' => (string) json_encode(array_filter($wert, fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_by' => $userId,
        ]);
        Cache::forget(self::CACHE);
    }
}
