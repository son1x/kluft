<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mitgliederabgleich (docs/mitgliederabgleich.md): je Veranstaltung
 * einschaltbar; je Anmeldung nur das Ergebnis, keine Kopie der
 * Mitgliederdaten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('member_check')->default(false)->after('waitlist_enabled');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            // mitglied | ehemalig | nicht_gefunden | unklar | kein_mitglied
            $table->string('member_status', 20)->nullable()->after('guardian_consent');
            $table->string('member_number', 40)->nullable()->after('member_status');
            $table->string('member_group', 150)->nullable()->after('member_number');
            $table->boolean('member_manual')->default(false)->after('member_group');
            $table->dateTime('member_checked_at')->nullable()->after('member_manual');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['member_status', 'member_number', 'member_group', 'member_manual', 'member_checked_at']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('member_check');
        });
    }
};
