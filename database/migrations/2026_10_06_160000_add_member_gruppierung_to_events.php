<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Veranstaltungen nur für Mitglieder einer Gruppierung (Stamm oder Bezirk):
 * Einstellung an der Veranstaltung, Ergebnis des Abgleichs an der Anmeldung
 * (true = eigene Gruppierung, false = andere, null = unbekannt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('member_gruppierung_id')->nullable()->after('member_check')->constrained('gruppierungen')->nullOnDelete();
        });
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->boolean('member_own_group')->nullable()->after('member_group');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('member_own_group');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_gruppierung_id');
        });
    }
};
