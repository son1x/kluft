<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Präventions-Nachweise der Leitenden (docs/praevention.md): nur Daten,
 * keine Dokumente und keine Inhalte des Führungszeugnisses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('praevention_nachweise', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->nullable()->constrained('people')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email')->nullable()->index();
            $table->string('art', 20);
            $table->string('bezeichnung', 150)->nullable();
            $table->date('datum')->nullable();
            $table->date('gueltig_bis')->index();
            $table->unsignedSmallInteger('erinnert_stufe')->nullable();
            $table->foreignId('erfasst_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('praevention_nachweise');
    }
};
