<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beitrag ↔ Veranstaltung: verknüpfte Beiträge zeigen einen Anmelde-Kasten.
 * Die Einladung zur Diözesanversammlung wird gleich verknüpft; der
 * schlichte Anmeldelink im Text entfällt dann (der Kasten ersetzt ihn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('cover_media_id')->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
