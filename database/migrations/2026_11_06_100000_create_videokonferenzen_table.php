<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Videokonferenzen auf meet.dpsg-trier.de (docs/videokonferenzen.md):
 * Titel, Beginn, Raumname. Moderator-Tokens werden nicht gespeichert,
 * sondern bei jedem Aufruf aus dem Secret berechnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videokonferenzen', function (Blueprint $table) {
            $table->id();
            $table->string('titel', 100);
            $table->dateTime('beginn')->comment('Ortszeit wie alle Zeiten in Kluft (app.timezone)');
            $table->string('raum', 60)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('beginn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videokonferenzen');
    }
};
