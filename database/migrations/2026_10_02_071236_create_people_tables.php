<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personenvorstellung (Diözesanleitung, Diözesanbüro) und Gruppenfotos mit
 * anklickbaren Personen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('gremium', 30)->index(); // Schlüssel aus config('dpsg.gremien')
            $table->string('subgroup')->nullable(); // Zwischenüberschrift, z. B. "Diözesanvorstand"
            $table->string('name');
            $table->string('role')->nullable(); // Funktion
            $table->string('stage', 20)->nullable(); // Farbe des Initialen-Kreises
            $table->string('email')->nullable(); // Funktionsadresse
            $table->text('bio')->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media')->nullOnDelete();
            // Veröffentlichung von Name, Funktion, Text und ggf. Foto ist mit
            // der Person abgestimmt (KDG, Pflichtenheft 9.1).
            $table->boolean('consent')->default(false);
            $table->boolean('active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('team_photos', function (Blueprint $table) {
            $table->id();
            $table->string('gremium', 30)->index();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('note')->nullable(); // z. B. wer fehlt
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('team_photo_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->decimal('x', 5, 2); // Gesichtsmitte in % der Bildbreite
            $table->decimal('y', 5, 2); // … und der Bildhöhe
            $table->unique(['team_photo_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_photo_spots');
        Schema::dropIfExists('team_photos');
        Schema::dropIfExists('people');
    }
};
