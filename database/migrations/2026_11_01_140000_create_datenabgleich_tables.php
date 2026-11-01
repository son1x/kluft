<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NaMi-Datenabgleich (docs/pflichtenheft-nami-datenabgleich.md): Meldungen
 * aus dem Formular „Meine Daten aktualisieren“ und die Zeilen der
 * Unterschiedsliste. Personendaten verschlüsselt; E-Mail-Adressen nur als
 * Hash für Datenschutz-Anfragen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datenmeldungen', function (Blueprint $table) {
            $table->id();
            $table->text('daten')->comment('verschlüsselt');
            $table->string('email_hash', 64)->nullable()->index();
            $table->string('email_eltern_hash', 64)->nullable()->index();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('bestaetigt_am')->nullable();
            $table->timestamps();
        });

        Schema::create('datenabgleich_zeilen', function (Blueprint $table) {
            $table->id();
            $table->string('quelle', 12)->comment('meldung | anmeldung');
            $table->unsignedBigInteger('quelle_id');
            $table->string('status', 16)->comment('abweichung | stimmt | nicht_gefunden | unklar');
            $table->text('ergebnis')->comment('verschlüsselt: Name, Mitgliedsnummer, Felder');
            $table->timestamp('erledigt_am')->nullable();
            $table->foreignId('erledigt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['quelle', 'quelle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datenabgleich_zeilen');
        Schema::dropIfExists('datenmeldungen');
    }
};
