<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Nachrichten-Eingang: Verlauf des Versands (Eingang, erneut gesendet,
| weitergeleitet) mit Ergebnis „mail“, „log“ (nur ins Server-Protokoll) oder
| „fehler“. Ältere Nachrichten haben keinen Verlauf.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->json('versand')->nullable()->after('zugestellt');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('versand');
        });
    }
};
