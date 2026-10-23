<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roden: keine Gruppenzelte über die Sammelbestellung (Wunsch Vorstand,
 * 21.10.2026). Weitere Sperren pflegt die Sammelbestellung im Backend.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('dpsg.instanz') !== 'roden' || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }
        if (! DB::table('shop_sperren')->where('typ', 'kategorie')->where('wert', 'zelte')->exists()) {
            DB::table('shop_sperren')->insert([
                'typ' => 'kategorie', 'wert' => 'zelte', 'notiz' => 'Gruppenzelte bestellt der Stamm selbst.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Im Backend unter Sammelbestellung → Sperrliste änderbar.
    }
};
