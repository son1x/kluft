<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        DB::table('people')->whereNull('badge')->whereNotNull('stage')->update(['badge' => DB::raw('stage')]);
        DB::table('people')->where('name', 'Bianca Boltze')->whereNull('badge')->update(['badge' => 'inklusion']);
        DB::table('people')->where('name', 'Jannis Odrosek')->update(['badge' => 'bolivien', 'section' => 'bolivien']);
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['section']);
            $table->dropColumn(['badge', 'section']);
        });
    }
};
