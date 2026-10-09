<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigene Benutzerverwaltung ohne Microsoft 365 (Meilenstein Stämme):
 * Anmeldeweg je Konto, Person oder Rollen-Zugang (Funktionspostfach) und
 * auf Wunsch die Prüfung der Mitgliedschaft in NaMi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 10)->default('entra')->after('status')->comment('entra = Microsoft 365, email = Einmalcode per E-Mail');
            $table->string('kind', 10)->default('person')->after('login')->comment('person = namentlich, rolle = Rollen-Zugang über ein Funktionspostfach');
            $table->string('member_number', 20)->nullable()->after('kind')->comment('DPSG-Mitgliedsnummer, nur für namentliche Zugänge');
            $table->timestamp('member_checked_at')->nullable()->after('member_number');
            $table->string('member_check_result')->nullable()->after('member_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['login', 'kind', 'member_number', 'member_checked_at', 'member_check_result']);
        });
    }
};
