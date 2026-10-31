<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuschussanträge (docs/pflichtenheft-zuschuesse.md, Phase 1): Angaben zum
 * Zuschuss an der Veranstaltung, Funktion und Anwesenheit je Anmeldung.
 * Personendaten (Straße, Geburtsdatum, Geschlecht) liegen verschlüsselt in
 * event_registrations.data wie die übrigen Formularfelder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('zuschuss')->default(false);
            $table->string('zuschuss_art', 20)->nullable()->comment('freizeit | bildung | schulung | sonstige');
            $table->json('zuschuss_geber')->nullable()->comment('z. B. ["land","kreis_sls","stadt_sls","bistum_trier"]');
            $table->json('zuschuss_finanzen')->nullable()->comment('Kosten- und Finanzierungsplan in Cent');
            $table->boolean('zuschuss_fern')->default(false)->comment('Ziel weiter als 250 km (Stadt Saarlouis)');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('funktion', 2)->default('TN')->comment('TN, B (Betreuung), R (Referent*in), L (Leitung)');
            $table->string('kreis', 5)->nullable()->comment('nur wenn abweichend von der PLZ');
            $table->boolean('juleica')->default(false);
            $table->boolean('anwesend')->nullable()->comment('null = nicht erfasst (zählt als anwesend)');
            $table->json('anwesend_tage')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', fn (Blueprint $t) => $t->dropColumn(['funktion', 'kreis', 'juleica', 'anwesend', 'anwesend_tage']));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['zuschuss', 'zuschuss_art', 'zuschuss_geber', 'zuschuss_finanzen', 'zuschuss_fern']));
    }
};
