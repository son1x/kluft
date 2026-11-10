<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialverleih (docs/materialverleih.md): Material und Reservierungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materialien', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('kategorie', 80)->nullable();
            $table->text('beschreibung')->nullable();
            $table->string('bild')->nullable();
            $table->unsignedSmallInteger('menge')->default(1);
            $table->string('ort', 150)->nullable();
            $table->text('hinweise')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });

        Schema::create('material_reservierungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materialien')->cascadeOnDelete();
            $table->unsignedSmallInteger('menge')->default(1);
            $table->date('von');
            $table->date('bis');
            $table->string('name', 150);
            $table->string('email')->nullable()->index();
            $table->string('telefon', 40)->nullable();
            $table->string('gruppierung', 150)->nullable();
            $table->string('zweck', 500)->nullable();
            $table->string('status', 15)->default('angefragt')->index();
            $table->text('maengel')->nullable();
            $table->text('notiz')->nullable();
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->date('erinnert_am')->nullable();
            $table->timestamps();
            $table->index(['material_id', 'von', 'bis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_reservierungen');
        Schema::dropIfExists('materialien');
    }
};
