<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigene Altersangabe einer Stufe für die Website, z. B. „3. bis 5. Klasse“
 * statt „7 bis 11 Jahre“ (Verwaltung → Stufen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_ages', function (Blueprint $table) {
            $table->string('text', 80)->nullable()->after('to');
        });
    }

    public function down(): void
    {
        Schema::table('stage_ages', function (Blueprint $table) {
            $table->dropColumn('text');
        });
    }
};
