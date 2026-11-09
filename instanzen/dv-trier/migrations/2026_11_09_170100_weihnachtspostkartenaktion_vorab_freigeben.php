<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Weihnachtspostkartenaktion: bis zum Livegang (21.11.2026) läuft die
 * Anmeldung über test.dpsg-trier.de, eingebunden als iframe in die alte
 * WordPress-Seite. Dafür „Vorab extern freigeben“ einschalten; nach dem
 * Livegang im Backend wieder ausschalten (schadet aber auch nicht).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')->where('slug', 'weihnachtspostkartenaktion-2026')->update(['extern_freigegeben' => true]);
    }

    public function down(): void
    {
        DB::table('events')->where('slug', 'weihnachtspostkartenaktion-2026')->update(['extern_freigegeben' => false]);
    }
};
