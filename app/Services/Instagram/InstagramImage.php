<?php

namespace App\Services\Instagram;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Bereitet ein Mediathek-Bild für Instagram auf: JPEG, Seitenverhältnis
 * zwischen 4:5 und 1,91:1 (mittig zugeschnitten), max. 1440 px breit.
 * Instagram lädt das Bild selbst über eine öffentliche URL.
 */
class InstagramImage
{
    private const MIN_RATIO = 4 / 5;

    private const MAX_RATIO = 1.91;

    private const MAX_WIDTH = 1440;

    /**
     * @return string Pfad auf der Public-Disk
     */
    public function prepare(Media $media): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($media->path)) {
            throw new InstagramException('Die Bilddatei fehlt in der Mediathek.');
        }
        if (! function_exists('imagecreatefromstring')) {
            throw new InstagramException('Bildumwandlung auf dem Server nicht verfügbar (PHP-GD fehlt).');
        }

        $source = @imagecreatefromstring($disk->get($media->path));
        if ($source === false) {
            throw new InstagramException('Das Titelbild konnte nicht gelesen werden.');
        }

        [$w, $h] = [imagesx($source), imagesy($source)];
        [$cropW, $cropH] = [$w, $h];
        if ($w / $h < self::MIN_RATIO) {
            $cropH = (int) round($w / self::MIN_RATIO);
        } elseif ($w / $h > self::MAX_RATIO) {
            $cropW = (int) round($h * self::MAX_RATIO);
        }

        $targetW = min($cropW, self::MAX_WIDTH);
        $targetH = (int) round($cropH * $targetW / $cropW);

        $target = imagecreatetruecolor($targetW, $targetH);
        // Transparenz (PNG/WebP) auf weißem Grund
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, intdiv($w - $cropW, 2), intdiv($h - $cropH, 2), $targetW, $targetH, $cropW, $cropH);

        ob_start();
        imagejpeg($target, null, 88);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        $path = 'instagram/'.bin2hex(random_bytes(16)).'.jpg';
        $disk->put($path, $jpeg);

        return $path;
    }
}
