<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datenschutz-Verbesserungen (docs/datenschutz-ueberblick.md):
 * frühere Löschung sensibler Angaben, freiwillige Foto-Einwilligung je
 * Anmeldung und Vorwarnung vor dem Sperren ungenutzter Konten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('foto_einwilligung')->default(false);
            $table->timestamp('sensible_geloescht_at')->nullable();
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->boolean('foto_einwilligung')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('inaktiv_gewarnt_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['foto_einwilligung', 'sensible_geloescht_at']);
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('foto_einwilligung');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('inaktiv_gewarnt_at');
        });
    }
};
