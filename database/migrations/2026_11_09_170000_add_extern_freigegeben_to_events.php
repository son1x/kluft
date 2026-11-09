<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Veranstaltung vorab extern freigeben: auf einem geschlossenen Testsystem
 * öffentlich erreichbar, Mails zu ihren Anmeldungen gehen echt raus, und
 * das Formular darf als iframe eingebettet werden (dpsg.einbetten_von).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('extern_freigegeben')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('extern_freigegeben');
        });
    }
};
