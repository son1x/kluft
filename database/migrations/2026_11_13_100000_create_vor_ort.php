<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vor Ort (docs/vor-ort.md): Anwesenheit je Tag an der Anmeldung und das
 * Protokoll der Ereignisse, die Geräte schicken (jede Kennung genau einmal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->json('vor_ort_tage')->nullable();
        });

        Schema::create('vor_ort_ereignisse', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('typ', 30);
            $table->text('daten');
            $table->string('ergebnis', 20);
            $table->timestamp('erfasst_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vor_ort_ereignisse');
        Schema::table('event_registrations', fn (Blueprint $t) => $t->dropColumn('vor_ort_tage'));
    }
};
