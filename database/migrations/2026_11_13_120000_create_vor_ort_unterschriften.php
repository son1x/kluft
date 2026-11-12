<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vor Ort, Stufe 2 (docs/vor-ort.md): digitale Unterschrift je Person und
 * Veranstaltung für die Zuschuss-Teilnahmeliste, dazu die festgeschriebene
 * Liste an der Veranstaltung. Einzelunterschriften werden nach dem
 * Festschreiben gelöscht (loeschen_am).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vor_ort_unterschriften', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('bild');
            $table->string('pruefsumme', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('unterschrieben_am')->nullable();
            $table->timestamp('loeschen_am')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('teilnahmeliste_datei')->nullable();
            $table->timestamp('teilnahmeliste_am')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vor_ort_unterschriften');
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['teilnahmeliste_datei', 'teilnahmeliste_am']));
    }
};
