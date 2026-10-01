<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nextcloud-Kalender, s. Pflichtenheft 6.5 und 8.2 („calendars“,
 * „calendar_events_cache“). Gespeichert werden nur freigegebene Felder –
 * keine Organizer-/Teilnehmerdaten (F-CAL-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->text('ics_url')->comment('Verschlüsselt (encrypted-Cast)');
            $table->string('color', 7)->default('#003056');
            $table->enum('stage', ['biber', 'woelflinge', 'jupfi', 'pfadi', 'rover'])->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_hash', 64)->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('calendar_events_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('uid');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('title');
            $table->string('location')->nullable();
            $table->text('description')->nullable();

            $table->index(['starts_at', 'calendar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events_cache');
        Schema::dropIfExists('calendars');
    }
};
