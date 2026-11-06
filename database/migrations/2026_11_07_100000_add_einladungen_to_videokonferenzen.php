<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einladungen per Mail (docs/videokonferenzen.md): Adressen der Moderation
 * und der Teilnehmenden, verschlüsselt, 7 Tage nach der Konferenz gelöscht
 * (videokonferenzen:aufraeumen). Dauer für den Kalendereintrag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videokonferenzen', function (Blueprint $table) {
            $table->unsignedSmallInteger('dauer_minuten')->default(60)->after('beginn');
            $table->text('moderation_emails')->nullable()->after('raum');
            $table->text('teilnehmende_emails')->nullable()->after('moderation_emails');
            $table->timestamp('eingeladen_am')->nullable()->after('teilnehmende_emails');
        });
    }

    public function down(): void
    {
        Schema::table('videokonferenzen', function (Blueprint $table) {
            $table->dropColumn(['dauer_minuten', 'moderation_emails', 'teilnehmende_emails', 'eingeladen_am']);
        });
    }
};
