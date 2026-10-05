<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bezirke und Stämme, s. Pflichtenheft 6.11 und 8.2 („gruppierungen“,
 * „gruppierung_stufen“).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gruppierungen', function (Blueprint $table) {
            $table->id();
            $table->enum('typ', ['bezirk', 'stamm']);
            $table->foreignId('parent_id')->nullable()->constrained('gruppierungen')->nullOnDelete()->comment('Bezirk eines Stammes');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('strasse')->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('ort')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->foreignId('position_gesetzt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kontakt_email')->nullable()->comment('Funktionsadresse, keine private Adresse (F-MAP-08)');
            $table->string('kontakt_telefon')->nullable();
            $table->string('ansprechperson')->nullable()->comment('Nur mit Einwilligung der Person (F-MAP-08)');
            $table->boolean('ansprechperson_einwilligung')->default(false);
            $table->string('website_url')->nullable();
            $table->string('social_url')->nullable();
            $table->text('beschreibung')->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('gruppierung_stufen', function (Blueprint $table) {
            $table->foreignId('gruppierung_id')->constrained('gruppierungen')->cascadeOnDelete();
            $table->enum('stufe', ['biber', 'woelflinge', 'jupfi', 'pfadi', 'rover']);
            $table->primary(['gruppierung_id', 'stufe']);
        });

        // Den Ausgangsbestand (beim DV die zehn Bezirke) legt der Seeder der
        // Instanz an (instanzen/<schlüssel>/seeders).
    }

    public function down(): void
    {
        Schema::dropIfExists('gruppierung_stufen');
        Schema::dropIfExists('gruppierungen');
    }
};
