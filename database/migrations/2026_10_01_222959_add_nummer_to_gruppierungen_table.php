<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DPSG-Nummer (Diözesanverband-Bezirk-Stamm, z. B. 21-01-08) für
 * Sortierung und Anzeige. Die Stämme des DV legt dessen Seeder an
 * (instanzen/dv-trier/seeders/StaemmeSeeder.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gruppierungen', function (Blueprint $table) {
            $table->string('nummer', 8)->nullable()->unique()->after('typ')->comment('DPSG-Nummer DV-Bezirk-Stamm, z. B. 21-01-08');
        });
    }

    public function down(): void
    {
        Schema::table('gruppierungen', function (Blueprint $table) {
            $table->dropUnique(['nummer']);
            $table->dropColumn('nummer');
        });
    }
};
