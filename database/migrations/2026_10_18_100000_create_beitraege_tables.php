<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mitgliedsbeiträge eines Stamms (Kasse → Mitgliedsbeiträge): Mitglieder aus
 * NaMi (nur Name, Mitgliedsnummer, Beitragsart, Ein- und Austritt), je
 * Halbjahr ein Beitrag mit Soll und Zahlung, dazu die Rechnung des
 * Bundesverbands für den Abgleich.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beitrags_mitglieder', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nami_id')->unique();
            $table->string('mitgliedsnummer', 20)->nullable();
            $table->string('vorname', 100);
            $table->string('nachname', 100);
            $table->string('beitragsart', 100)->nullable();
            $table->date('eintritt')->nullable();
            $table->date('austritt')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->unsignedInteger('nami_version')->nullable();
            $table->timestamp('abgerufen_am')->nullable();
            $table->timestamps();
        });
        Schema::create('beitraege', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beitrags_mitglied_id')->constrained('beitrags_mitglieder')->cascadeOnDelete();
            $table->string('halbjahr', 7)->index();          // z. B. 2026-2
            $table->string('beitragsart', 100)->nullable();  // Stand beim Anlegen
            $table->unsignedInteger('soll_cent')->default(0);
            $table->unsignedInteger('bezahlt_cent')->nullable();
            $table->date('bezahlt_am')->nullable();
            $table->string('zahlart', 20)->nullable();
            $table->string('notiz', 255)->nullable();
            $table->foreignId('erfasst_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['beitrags_mitglied_id', 'halbjahr']);
        });
        Schema::create('beitrags_abrechnungen', function (Blueprint $table) {
            $table->id();
            $table->string('halbjahr', 7)->unique();
            $table->unsignedInteger('mitglieder')->nullable();       // laut Rechnung
            $table->unsignedInteger('rechnungsbetrag_cent')->nullable();
            $table->date('rechnungsdatum')->nullable();
            $table->date('bezahlt_am')->nullable();
            $table->string('notiz', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beitrags_abrechnungen');
        Schema::dropIfExists('beitraege');
        Schema::dropIfExists('beitrags_mitglieder');
    }
};
