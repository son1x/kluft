<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shop_bestellungen.test kam nachträglich in 2026_10_23_090000. Auf Roden
 * war die Tabelle da schon angelegt (Deploy 23.10.2026), daher hier noch
 * einmal, nur wenn die Spalte fehlt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_bestellungen') && ! Schema::hasColumn('shop_bestellungen', 'test')) {
            Schema::table('shop_bestellungen', function (Blueprint $table) {
                $table->boolean('test')->default(false)->comment('im Testbetrieb bestellt, löschbar');
            });
        }
    }

    public function down(): void
    {
        // Die Spalte gehört zu 2026_10_23_090000; deren down() entfernt die Tabelle.
    }
};
