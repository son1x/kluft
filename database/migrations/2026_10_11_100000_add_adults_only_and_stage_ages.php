<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Veranstaltungen für Erwachsene (eigenverantwortliche Anmeldung, ohne
 * Abschnitt für Personensorgeberechtigte) und Altersangaben der Stufen, die
 * eine Gruppierung im erlaubten Rahmen selbst festlegt (Verwaltung → Stufen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('adults_only')->default(false)->after('waitlist_enabled');
        });
        Schema::create('stage_ages', function (Blueprint $table) {
            $table->string('stage', 32)->primary();
            $table->unsignedTinyInteger('from');
            $table->unsignedTinyInteger('to');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_ages');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('adults_only');
        });
    }
};
