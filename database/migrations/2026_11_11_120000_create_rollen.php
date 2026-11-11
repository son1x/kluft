<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigene Rollen der Rechte-Matrix (docs/rollen.md): Name, Geltung (ganze
 * Website oder je Bereich) und angehakte Rechte. Mitgelieferte Rollen stehen
 * nicht hier, sie sind Vorlagen im Code (App\Support\Rechte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollen', function (Blueprint $table) {
            $table->id();
            $table->string('schluessel', 30)->unique();
            $table->string('name', 80);
            $table->string('beschreibung', 300)->nullable();
            $table->string('geltung', 10)->default('global');
            $table->json('rechte');
            $table->string('vorlage', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rollen');
    }
};
