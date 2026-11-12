<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Die WordPress-Seiten mit eingebetteter Anmeldung (vor dem Livegang) sollen
 * auch danach funktionieren: Weiterleitung auf die Veranstaltung in Kluft.
 * Bestehende Einträge (z. B. im Backend geändert) bleiben unberührt.
 */
return new class extends Migration
{
    private const WEITERLEITUNGEN = [
        '/adventszauber-2026' => '/veranstaltungen/adventszauber-2026',
        '/spiritueller-hike-2027' => '/veranstaltungen/spiritueller-hike-2027',
        '/woe-weihnachtsaktion-2026' => '/veranstaltungen/weihnachtspostkartenaktion-2026',
    ];

    /** In Tests nur auf ausdrücklichen Wunsch (andere Tests zählen Weiterleitungen). */
    public bool $auchInTests = false;

    public function up(): void
    {
        if (app()->runningUnitTests() && ! $this->auchInTests) {
            return;
        }
        foreach (self::WEITERLEITUNGEN as $von => $nach) {
            if (DB::table('redirects')->where('from_path', $von)->exists()) {
                continue;
            }
            DB::table('redirects')->insert([
                'from_path' => $von, 'to_path' => $nach, 'status_code' => 301, 'source' => 'manuell',
                'hits' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Cache::forget('redirects:map');
    }

    public function down(): void
    {
        DB::table('redirects')->whereIn('from_path', array_keys(self::WEITERLEITUNGEN))->where('hits', 0)->delete();
        Cache::forget('redirects:map');
    }
};
