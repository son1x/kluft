<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teilnahmebeitrag (docs/zahlungen.md): Preisstufen je Veranstaltung
 * (Bezeichnung + Betrag in Cent) und die gewählte Stufe je Anmeldung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('preise')->nullable();
            $table->unsignedSmallInteger('zahlungsfrist_tage')->nullable();
        });
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('preis_bezeichnung', 100)->nullable();
            $table->unsignedInteger('preis_cent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['preis_bezeichnung', 'preis_cent']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['preise', 'zahlungsfrist_tage']);
        });
    }
};
