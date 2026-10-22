<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schnuppermitglieder aus NaMi stehen mit in der Beitragsliste – markiert
 * und ohne Beitrag („nichts zu zahlen“).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beitrags_mitglieder', function (Blueprint $table) {
            $table->boolean('schnupper')->default(false)->after('beitragsart');
        });
    }

    public function down(): void
    {
        Schema::table('beitrags_mitglieder', function (Blueprint $table) {
            $table->dropColumn('schnupper');
        });
    }
};
