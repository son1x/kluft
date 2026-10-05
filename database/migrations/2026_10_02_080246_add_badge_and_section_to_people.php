<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kreis-Gestaltung je Person (Stufenfarbe oder Muster wie Punkte-Halstuch
 * „Ich-Du-Wir“ bzw. Bolivien-Streifen) und Ansprechperson einer Sektion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('badge', 20)->nullable()->after('stage');
            $table->string('section', 30)->nullable()->after('gremium')->index();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['section']);
            $table->dropColumn(['badge', 'section']);
        });
    }
};
