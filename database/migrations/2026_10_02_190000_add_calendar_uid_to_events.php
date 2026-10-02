<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft eine Veranstaltung mit dem Nextcloud-Termin, aus dem sie
 * angelegt wurde – damit der Kalender sie auch nach Titeländerungen nur
 * einmal zeigt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('calendar_uid', 250)->nullable()->after('created_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['calendar_uid']);
            $table->dropColumn('calendar_uid');
        });
    }
};
