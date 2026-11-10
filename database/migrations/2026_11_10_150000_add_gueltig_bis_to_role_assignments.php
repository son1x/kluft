<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen mit Ablaufdatum: „gültig bis“ (einschließlich). Am Tag danach
 * entfernt `rollen:ablauf` die Zuweisung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->date('gueltig_bis')->nullable()->after('scope_key');
        });
    }

    public function down(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->dropColumn('gueltig_bis');
        });
    }
};
