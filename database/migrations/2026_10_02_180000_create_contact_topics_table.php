<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Themen des Kontaktformulars (F-CMS-09). Empfänger: feste Adresse
 * (Funktionsadresse) oder – ohne Adresse – die Stufenreferent*innen bzw.
 * Fachbereichs-Ansprechpersonen, sonst das Diözesanbüro. Nachrichten
 * selbst werden nicht gespeichert, nur per Mail weitergeleitet. Die
 * Startthemen legt der Seeder der Instanz an.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_topics', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('slug', 60)->unique()->comment('Für Links wie /kontakt?thema=woelflinge');
            $table->string('email', 254)->nullable()->comment('Feste Empfängeradresse; leer = Ansprechpersonen bzw. Diözesanbüro');
            $table->string('stage', 20)->nullable();
            $table->string('section', 60)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_topics');
    }
};
