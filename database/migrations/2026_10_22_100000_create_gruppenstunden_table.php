<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gruppenstunden je Stufe (Stammes-Websites), gepflegt unter Verwaltung →
 * Gruppenstunden. Mehrere Gruppen einer Stufe sind möglich (z. B. zwei
 * Wölflingsmeuten); dann unterscheidet sie der Name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gruppenstunden', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 20);
            $table->string('name', 80)->nullable();
            $table->unsignedTinyInteger('wochentag')->nullable()->comment('1 = Montag … 7 = Sonntag');
            $table->time('beginn')->nullable();
            $table->time('ende')->nullable();
            $table->string('ort', 160)->nullable()->comment('leer = Adresse des Stamms');
            $table->string('hinweis', 160)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['stage', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gruppenstunden');
    }
};
