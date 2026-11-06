<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adressbuch (docs/adressbuch.md): Kontakte mit Name und E-Mail, auch
 * Externe ohne Konto, und Verteiler (gemeinsam oder nur für die Person,
 * die ihn angelegt hat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adressbuch_kontakte', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->nullable();
            $table->string('email')->unique();
            $table->string('organisation', 150)->nullable();
            $table->text('notiz')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('adressbuch_verteiler', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('beschreibung')->nullable();
            $table->boolean('privat')->default(false)->comment('nur für user_id sichtbar');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('adressbuch_kontakt_verteiler', function (Blueprint $table) {
            $table->foreignId('verteiler_id')->constrained('adressbuch_verteiler')->cascadeOnDelete();
            $table->foreignId('kontakt_id')->constrained('adressbuch_kontakte')->cascadeOnDelete();
            $table->primary(['verteiler_id', 'kontakt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adressbuch_kontakt_verteiler');
        Schema::dropIfExists('adressbuch_verteiler');
        Schema::dropIfExists('adressbuch_kontakte');
    }
};
