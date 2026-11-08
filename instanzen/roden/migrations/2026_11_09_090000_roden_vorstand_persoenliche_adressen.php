<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roden: Kimberly Lamprecht und Flavio Russello zeigen auf der Seite
 * „Stammesvorstand“ ihre persönliche Adresse (vorname@dpsg-roden.de nach
 * dem Schema der Instanz) statt vorstand@dpsg-roden.de. Die Migration
 * 2026_10_19_101500 hatte vorstand@ fest eingetragen. Geändert wird nur,
 * solange dort noch genau diese Adresse steht; die Vorstandsadresse im
 * Seitentext bleibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('dpsg.instanz') !== 'roden') {
            return;
        }

        DB::table('people')
            ->where('gremium', 'leitung')
            ->whereIn('name', ['Kimberly Lamprecht', 'Flavio Russello'])
            ->where('email', 'vorstand@dpsg-roden.de')
            ->update(['email' => null, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Adressen bleiben; im Backend änderbar.
    }
};
