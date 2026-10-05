<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Inhalts-Migration der Instanz dv-trier: Auf Cartoon 1 von „Stöbern &
 * Schmökern“ stand „PB BeratungsPower & Coaching“ statt „BP“ (Rückmeldung
 * Lisa). Ersetzt die Bilddatei nur, wenn sie noch die alte ist, und
 * korrigiert Alternativtext und Seiteninhalt.
 */
return new class extends Migration
{
    private const PATH = 'media/2026/beraten-online-cartoon-1.jpg';

    private const OLD_MD5 = '26f79c3daf3a13b3f8a1ed91401557e2';

    public function up(): void
    {
        $disk = Storage::disk('public');
        $source = base_path('instanzen/dv-trier/dateien/beraten-online/cartoon-1.jpg');
        if (! app()->runningUnitTests() && $disk->exists(self::PATH) && md5((string) $disk->get(self::PATH)) === self::OLD_MD5 && is_file($source)) {
            $disk->put(self::PATH, (string) file_get_contents($source));
            DB::table('media')->where('path', self::PATH)->update(['size' => filesize($source), 'updated_at' => now()]);
        }

        DB::table('media')->where('path', self::PATH)->where('alt_text', 'like', '%– PB BeratungsPower%')
            ->update(['alt_text' => DB::raw("REPLACE(alt_text, '– PB BeratungsPower', '– BP BeratungsPower')"), 'updated_at' => now()]);

        // Neuer Dateistand: ?v=2 umgeht zwischengespeicherte Bilder im Browser.
        DB::table('pages')->where('body', 'like', '%beraten-online-cartoon-1.jpg"%')->get(['id', 'body'])
            ->each(fn ($page) => DB::table('pages')->where('id', $page->id)->update([
                'body' => str_replace(
                    ['– PB BeratungsPower', 'beraten-online-cartoon-1.jpg"'],
                    ['– BP BeratungsPower', 'beraten-online-cartoon-1.jpg?v=2"'],
                    (string) $page->body,
                ),
                'updated_at' => now(),
            ]));
    }

    public function down(): void {}
};
