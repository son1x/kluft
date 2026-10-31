<?php

namespace App\Support;

/**
 * Rubrik „Diözesanlager“: Lager sind Bereiche (config dpsg.sections) mit
 * `kind` => 'Diözesanlager'. Jedes Lager kann ein eigenes Aussehen
 * (`theme`, CSS-Klasse .lager-<theme>) haben und als Archiv (`archiv`)
 * markiert sein. Übersicht: CMS-Seite config dpsg.lager_page.
 */
class Lager
{
    public const KIND = 'Diözesanlager';

    /**
     * Alle Lager, das neueste zuerst.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function alle(): array
    {
        $lager = array_filter(config('dpsg.sections', []), fn (array $s) => ($s['kind'] ?? null) === self::KIND);
        uasort($lager, fn (array $a, array $b) => ($b['jahr'] ?? 0) <=> ($a['jahr'] ?? 0));

        return $lager;
    }

    public static function ist(?string $section): bool
    {
        return $section !== null && config("dpsg.sections.{$section}.kind") === self::KIND;
    }

    /**
     * CSS-Klassen für den Inhaltsbereich eines Lagers mit eigenem Aussehen,
     * sonst leer (dann gilt das DPSG-Design).
     */
    public static function klasse(?string $section): string
    {
        $theme = $section ? config("dpsg.sections.{$section}.theme") : null;

        return self::ist($section) && is_string($theme) && preg_match('/^[a-z0-9-]+$/', $theme)
            ? 'lager lager-'.$theme
            : '';
    }

    public static function uebersichtUrl(): ?string
    {
        $slug = config('dpsg.lager_page');

        return $slug ? url('/'.$slug) : null;
    }
}
