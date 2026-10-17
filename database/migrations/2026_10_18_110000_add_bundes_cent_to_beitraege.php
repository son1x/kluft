<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bundesanteil je Beitrag (Soll = Bundesbeitrag + Stammesanteil), damit
 * die Summe mit der Rechnung des Bundesverbands verglichen werden kann.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beitraege', function (Blueprint $table) {
            $table->unsignedInteger('bundes_cent')->nullable()->after('soll_cent');
        });
    }

    public function down(): void
    {
        Schema::table('beitraege', function (Blueprint $table) {
            $table->dropColumn('bundes_cent');
        });
    }
};
