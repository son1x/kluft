<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachrichten an die Teilnehmenden einer Veranstaltung (Rundmail aus dem
 * Reiter „Teilnehmende“ und automatische Erinnerung), docs/plan-ehrenamt.md Nr. 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_nachrichten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('art', 20)->default('nachricht'); // nachricht | erinnerung
            $table->string('betreff');
            $table->text('text');
            $table->json('an')->nullable(); // Status der Empfänger*innen
            $table->unsignedInteger('empfaenger')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedTinyInteger('erinnerung_tage')->nullable()->after('verantwortlich_email');
            $table->text('erinnerung_text')->nullable()->after('erinnerung_tage');
            $table->timestamp('erinnerung_gesendet_am')->nullable()->after('erinnerung_text');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['erinnerung_tage', 'erinnerung_text', 'erinnerung_gesendet_am']);
        });
        Schema::dropIfExists('event_nachrichten');
    }
};
