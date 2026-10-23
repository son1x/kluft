<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umfragen (docs/pflichtenheft-umfragen.md): Umfragen und Vorlagen, Fragen
 * mit Bedingungen, Antworten (anonym nur mit Tagesdatum), Einladungen an
 * Teilnehmende einer Veranstaltung und das Gewinnspiel – getrennt von den
 * Antworten, damit es keine Zuordnung gibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umfragen', function (Blueprint $table) {
            $table->id();
            $table->string('titel', 200);
            $table->string('code', 32)->unique();             // Teil des öffentlichen Links
            $table->text('einleitung')->nullable();
            $table->text('danke')->nullable();
            $table->string('status', 20)->default('entwurf'); // entwurf | offen | geschlossen | archiviert
            $table->dateTime('beginnt_am')->nullable();
            $table->dateTime('endet_am')->nullable();
            $table->boolean('anonym')->default(true);
            $table->boolean('mehrfach')->default(false);      // mehrere Antworten aus einem Browser erlaubt
            $table->string('zugangscode', 50)->nullable();
            $table->boolean('ist_vorlage')->default(false);
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->boolean('gewinnspiel')->default(false);
            $table->text('gewinnspiel_text')->nullable();
            $table->unsignedSmallInteger('aufbewahrung_monate')->default(12);
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('umfrage_fragen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('umfrage_id')->constrained('umfragen')->cascadeOnDelete();
            $table->string('schluessel', 36);                 // stabil für Bedingungen und Antworten
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('typ', 20);
            $table->string('text', 500);
            $table->text('hilfe')->nullable();
            $table->boolean('pflicht')->default(false);
            $table->boolean('kommentar')->default(false);
            $table->json('optionen')->nullable();
            $table->json('einstellungen')->nullable();
            $table->json('bedingung')->nullable();            // {frage: schluessel, werte: [...]}
            $table->timestamps();
            $table->unique(['umfrage_id', 'schluessel']);
        });

        Schema::create('umfrage_antworten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('umfrage_id')->constrained('umfragen')->cascadeOnDelete();
            $table->date('eingegangen_am');                   // anonym: nur der Tag
            $table->foreignId('event_registration_id')->nullable()->constrained('event_registrations')->nullOnDelete();
            $table->string('name', 200)->nullable();
            $table->string('email', 254)->nullable();
            $table->text('daten');                            // verschlüsselt: {schluessel: {wert, kommentar, sonstiges}}
        });

        Schema::create('umfrage_einladungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('umfrage_id')->constrained('umfragen')->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('eingeladen_am');
            $table->date('teilgenommen_am')->nullable();
            $table->unique(['umfrage_id', 'event_registration_id']);
        });

        Schema::create('umfrage_gewinnspiel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('umfrage_id')->constrained('umfragen')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('email', 254);
            $table->date('eingegangen_am');
            $table->boolean('gewonnen')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('umfrage_gewinnspiel');
        Schema::dropIfExists('umfrage_einladungen');
        Schema::dropIfExists('umfrage_antworten');
        Schema::dropIfExists('umfrage_fragen');
        Schema::dropIfExists('umfragen');
    }
};
