<?php

namespace App\Support;

/**
 * Welcher Kluft-Stand läuft: Git-Revision für den Versionstag im Backend.
 * Auf dem Server setzt der Deploy KLUFT_REVISION; lokal wird .git gelesen.
 */
class KluftVersion
{
    public static function revision(): ?string
    {
        $revision = trim((string) config('app.revision'));
        if ($revision === '') {
            $revision = (string) self::ausGit();
        }

        return preg_match('/^[0-9a-f]{7,40}$/', $revision) ? $revision : null;
    }

    public static function kurz(): ?string
    {
        $revision = self::revision();

        return $revision === null ? null : substr($revision, 0, 7);
    }

    public static function link(): ?string
    {
        $revision = self::revision();
        $repository = rtrim((string) config('app.repository'), '/');

        return $revision === null || $repository === '' ? null : $repository.'/commit/'.$revision;
    }

    private static function ausGit(): ?string
    {
        $git = base_path('.git');
        $head = @file_get_contents($git.'/HEAD');
        if ($head === false) {
            return null;
        }
        $head = trim($head);
        if (! str_starts_with($head, 'ref: ')) {
            return $head;
        }
        $ref = substr($head, 5);
        $datei = @file_get_contents($git.'/'.$ref);
        if ($datei !== false) {
            return trim($datei);
        }
        // Gepackte Referenzen (nach git gc).
        $gepackt = @file_get_contents($git.'/packed-refs');
        if ($gepackt !== false && preg_match('/^([0-9a-f]{40}) '.preg_quote($ref, '/').'$/m', $gepackt, $treffer)) {
            return $treffer[1];
        }

        return null;
    }
}
