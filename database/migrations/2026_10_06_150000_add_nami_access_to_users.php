<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persönlicher NaMi-Zugang im Profil (freiwillig): Mitgliedsnummer und
 * Passwort verschlüsselt (APP_KEY), dazu Zeitpunkt und Fehler der letzten
 * Anmeldung für die Ampel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('nami_user')->nullable();
            $table->text('nami_password')->nullable();
            $table->timestamp('nami_checked_at')->nullable();
            $table->string('nami_error', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nami_user', 'nami_password', 'nami_checked_at', 'nami_error']);
        });
    }
};
