<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuschussanträge Phase 2 (docs/pflichtenheft-zuschuesse.md): Angaben für
 * Bericht und Anträge an der Veranstaltung (Programm, Sachbericht, Thema,
 * Unterkunft …) und die Stammdaten der Leitenden für Zuschüsse (ZU-06,
 * verschlüsselt, nie auf der Website).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('zuschuss_bericht')->nullable();
        });
        Schema::table('people', function (Blueprint $table) {
            $table->text('zuschuss_daten')->nullable()->comment('verschlüsselt: Anschrift, Geburtsdatum, Geschlecht, Juleica');
        });
    }

    public function down(): void
    {
        Schema::table('people', fn (Blueprint $t) => $t->dropColumn('zuschuss_daten'));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('zuschuss_bericht'));
    }
};
