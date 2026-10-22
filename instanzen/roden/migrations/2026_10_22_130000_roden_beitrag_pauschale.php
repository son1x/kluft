<?php

use App\Support\Beitraege;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roden: einheitlicher Mitgliedsbeitrag von 24 € je Halbjahr (Stand
 * 22.10.2026), nur wenn die Kasse noch nichts eingetragen hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nur die laufende Website; eine Neuinstallation setzt den Beitrag aus angaben.json.
        if (config('dpsg.instanz') !== 'roden' || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }
        if (Beitraege::pauschale() === null && Beitraege::saetze() === []) {
            Beitraege::pauschaleSetzen(2400);
        }
    }

    public function down(): void
    {
        // Im Backend unter Kasse → Mitgliedsbeiträge → Beitragssätze änderbar.
    }
};
