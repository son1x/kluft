<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Till Mang ist Diözesanvorsitzender (nicht „Diözesanvorstand“ – das ist
 * das Gremium). Nur ändern, wenn noch der alte Wert eingetragen ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('people')->where('name', 'Till Mang')->where('role', 'Diözesanvorstand')
            ->update(['role' => 'Diözesanvorsitzender', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Rein inhaltlich; nichts zurückzuholen.
    }
};
