<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachrichten-Eingang (Funktion „Nachrichten-Eingang“): Nachrichten aus
 * dem Kontaktformular und Fragen zu Veranstaltungen, zusätzlich zur Mail an
 * die zuständige Person, damit die Verwaltung sie zentral sieht. Der Text
 * ist verschlüsselt; gelöscht wird nach dpsg.nachrichten.aufbewahrung_monate
 * (kontakt:prune).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('art', 20)->default('kontakt');               // kontakt | veranstaltung
            $table->foreignId('contact_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('thema', 200);                                 // Thema bzw. Veranstaltung beim Absenden
            $table->string('name', 100);
            $table->string('email', 254)->index();
            $table->string('betreff', 150)->nullable();
            $table->text('nachricht');                                    // verschlüsselt
            $table->json('empfaenger');
            $table->boolean('zugestellt')->default(true);
            $table->string('status', 20)->default('neu')->index();       // neu | in_bearbeitung | erledigt
            $table->foreignId('bearbeitet_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('bearbeitet_am')->nullable();
            $table->string('notiz', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
