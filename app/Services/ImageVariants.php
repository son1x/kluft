<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Erzeugt WebP-Varianten in festen Breiten (Pflichtenheft F-CMS-04,
 * 10.3 „Bildoptimierung (WebP, Lazy-Loading)“). Schlägt die Umwandlung
 * fehl (z. B. ohne WebP-Unterstützung in PHP-GD), bleibt das Original
 * in Gebrauch – die Seite funktioniert weiter.
 */
class ImageVariants
{
    public const WIDTHS = [480, 960, 1600];

    private const QUALITY = 80;

    public function generate(Media $media): void
    {
        $disk = Storage::disk('public');
        $this->deleteVariants($media);

        if (! str_starts_with((string) $media->mime, 'image/') || $media->mime === 'image/svg+xml' || ! $disk->exists($media->path)) {
            $media->forceFill(['variants' => null])->saveQuietly();

            return;
        }

        try {
            $source = function_exists('imagewebp') ? @imagecreatefromstring($disk->get($media->path)) : false;
            if ($source === false) {
                $media->forceFill(['variants' => null])->saveQuietly();

                return;
            }

            [$w, $h] = [imagesx($source), imagesy($source)];
            $variants = [];

            foreach (self::WIDTHS as $width) {
                // Nicht hochskalieren; die größte Variante darf so breit wie das Original sein.
                $target = min($width, $w);
                if (isset($variants[$target])) {
                    continue;
                }

                $height = (int) round($h * $target / $w);
                $img = imagecreatetruecolor($target, $height);
                imagealphablending($img, false);
                imagesavealpha($img, true);
                imagecopyresampled($img, $source, 0, 0, 0, 0, $target, $height, $w, $h);

                ob_start();
                imagewebp($img, null, self::QUALITY);
                // Kennung aus dem Pfad: ein ausgetauschtes Bild bekommt neue
                // Dateinamen, damit Browser nicht die alte Variante zeigen.
                $path = 'media/variants/'.$media->id.'-'.substr(md5($media->path), 0, 8).'-'.$target.'.webp';
                $disk->put($path, (string) ob_get_clean());
                imagedestroy($img);

                $variants[$target] = $path;

                if ($target === $w) {
                    break;
                }
            }

            imagedestroy($source);
            $media->forceFill(['variants' => $variants, 'width' => $w, 'height' => $h])->saveQuietly();
        } catch (Throwable $e) {
            report($e);
            $media->forceFill(['variants' => null])->saveQuietly();
        }
    }

    public function deleteVariants(Media $media): void
    {
        $paths = array_values($media->getOriginal('variants') ?? $media->variants ?? []);
        if ($paths) {
            Storage::disk('public')->delete($paths);
        }
    }
}
