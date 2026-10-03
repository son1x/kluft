<?php

use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Beitragsbild der DiVers 2026: Logo vor einer unscharfen Ansicht des
 * Jugendhauses (Bischöfliches Priesterseminar, Jesuitenstraße 13).
 *
 * Tauscht nur das ursprünglich eingespielte Bild aus; wurde es im Backend
 * ersetzt oder bearbeitet, bleibt alles, wie es ist.
 */
return new class extends Migration
{
    private const OLD_PATH = 'media/2026/divers-2026-titelbild.jpg';

    private const NEW_PATH = 'media/2026/divers-2026-titelbild-jugendhaus.jpg';

    private const SOURCE = 'database/content/divers-2026/divers-2026-titelbild-jugendhaus.jpg';

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $media = Media::query()->where('path', self::OLD_PATH)->where('credit', 'DPSG Diözesanverband Trier')->first();
        $source = base_path(self::SOURCE);
        if (! $media || ! is_file($source)) {
            return;
        }

        $disk = Storage::disk('public');
        $disk->put(self::NEW_PATH, (string) file_get_contents($source));

        // Neuer Pfad: Browser- und CDN-Caches holen das neue Bild, WebP-
        // Varianten entstehen über Media::saved() neu.
        $media->update([
            'path' => self::NEW_PATH,
            'size' => filesize($source),
            'width' => 1600,
            'height' => 900,
            'alt_text' => 'Rundes Logo der 91. Diözesanversammlung 2026 – Weltkugel mit Megafon und zwei Jugendlichen im Gespräch – vor einer unscharfen Ansicht des Jugendhauses im Priesterseminar Trier',
            // commons.wikimedia.org/wiki/File:Trier,_das_bischöfliche_Priesterseminar.jpg
            'credit' => 'Dguendel / Wikimedia Commons, CC BY 4.0 (Hintergrund, bearbeitet); Logo: DPSG Diözesanverband Trier',
        ]);

        if (! DB::table('media')->where('path', self::OLD_PATH)->exists()) {
            $disk->delete(self::OLD_PATH);
        }
    }

    public function down(): void
    {
        // Rein inhaltlich.
    }
};
