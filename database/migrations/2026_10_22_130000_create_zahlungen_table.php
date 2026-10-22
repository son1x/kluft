<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baustein „Zahlungen“ (docs/zahlungen.md): eine Zahlung je Forderung, am
 * Auslöser (Bestellung, Beitrag, Anmeldung …) polymorph angehängt. Keine
 * Personendaten – Name und Mail kommen vom Auslöser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zahlungen', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('zahlbar');
            $table->string('referenz', 35)->unique();          // Verwendungszweck, z. B. RH-2026-0012
            $table->string('zweck', 140);                      // Text für Liste und Aufforderung
            $table->unsignedInteger('betrag_cent');
            $table->unsignedInteger('bezahlt_cent')->nullable();
            $table->unsignedInteger('erstattet_cent')->default(0);
            $table->string('status', 20)->default('offen');    // offen | teilweise | bezahlt | erstattet | storniert
            $table->string('weg', 20)->default('ueberweisung');
            $table->string('anbieter_ref', 120)->nullable();   // später z. B. Stripe-Zahlungs-ID
            $table->date('faellig_am')->nullable();
            $table->date('bezahlt_am')->nullable();
            $table->date('erstattet_am')->nullable();
            $table->timestamp('aufgefordert_am')->nullable();
            $table->string('notiz', 500)->nullable();
            $table->foreignId('erfasst_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zahlungen');
    }
};
