<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Personen-Eintrag mit einem Konto verknüpfen (Mein Konto → „Mein Eintrag
| auf der Website“): Die Person pflegt Vorstellungstext, Foto und ihre
| Einwilligung selbst. Zuordnen darf nur die Verwaltung. Eine Person kann
| mehrere Einträge haben (z. B. Leitung und Arbeitskreis).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
