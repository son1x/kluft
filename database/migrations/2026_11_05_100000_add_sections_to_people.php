<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ansprechperson für mehrere Fachbereiche (Rückmeldung Till, 05.11.2026):
 * people.sections hält alle Bereiche, für die eine Person Ansprechperson
 * ist. people.section bleibt: beim FAK der eine Fachbereich, sonst der
 * erste aus sections (für Bestehendes, das nur ein Feld kennt).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('people', 'sections')) {
            Schema::table('people', function (Blueprint $table) {
                $table->json('sections')->nullable()->after('section');
            });
        }

        DB::table('people')->whereNotNull('section')->where('gremium', '!=', 'fak')->whereNull('sections')
            ->orderBy('id')->each(function (object $p) {
                DB::table('people')->where('id', $p->id)->update(['sections' => json_encode([$p->section])]);
            });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('sections');
        });
    }
};
