<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Info-Mails zu Anmeldungen je Veranstaltung: an die Sammeladresse
 * (Einstellung im Backend, Standard die Kontaktadresse) und an die
 * verantwortliche Person (ohne Angabe: wer die Veranstaltung angelegt hat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('info_sammeladresse')->default(true)->after('contact_email');
            $table->boolean('info_verantwortlich')->default(true)->after('info_sammeladresse');
            $table->string('verantwortlich_email')->nullable()->after('info_verantwortlich');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['info_sammeladresse', 'info_verantwortlich', 'verantwortlich_email']);
        });
    }
};
