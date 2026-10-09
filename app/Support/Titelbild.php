<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Page;

/**
 * Titelbild der Startseite: das der Seite „start“ (Mediathek) oder eines
 * der Instanz (config dpsg.titelbilder). Mit der Funktion
 * „Titelbild wechselt zufällig“ zieht jeder Aufruf eines aus allen.
 */
class Titelbild
{
    /**
     * @return array{media: ?Media, src: ?string, alt: string, credit: ?string, link: ?string, position: string}|null
     */
    public static function fuer(Page $start): ?array
    {
        $kandidaten = self::kandidaten($start);
        if ($kandidaten === []) {
            return null;
        }

        if (Features::enabled(Features::TITELBILD_WECHSEL) && count($kandidaten) > 1) {
            return $kandidaten[array_rand($kandidaten)];
        }

        return $kandidaten[0];
    }

    /**
     * @return list<array{media: ?Media, src: ?string, alt: string, credit: ?string, link: ?string, position: string}>
     */
    public static function kandidaten(Page $start): array
    {
        $kandidaten = [];
        if ($start->coverMedia) {
            $kandidaten[] = [
                'media' => $start->coverMedia,
                'src' => null,
                'alt' => (string) $start->coverMedia->alt_text,
                'credit' => $start->coverMedia->credit ?: null,
                'link' => null,
                'position' => '50% 50%',
            ];
        }

        foreach ((array) config('dpsg.titelbilder', []) as $bild) {
            if (! is_array($bild) || empty($bild['src'])) {
                continue;
            }
            $kandidaten[] = [
                'media' => null,
                'src' => (string) $bild['src'],
                'alt' => (string) ($bild['alt'] ?? ''),
                'credit' => isset($bild['credit']) ? (string) $bild['credit'] : null,
                'link' => isset($bild['link']) ? (string) $bild['link'] : null,
                'position' => self::position($bild['position'] ?? null),
            ];
        }

        return $kandidaten;
    }

    /** CSS object-position, nur einfache Angaben wie „60% 40%“ oder „center top“. */
    private static function position(mixed $wert): string
    {
        $wert = trim((string) $wert);

        return preg_match('/^[a-z0-9.% ]{1,40}$/', $wert) ? $wert : '50% 50%';
    }
}
