<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Familienanmeldung: mehrere Personen in einem Formular, eine
 * Bestätigungsmail; je Person eine Anmeldung, verbunden über
 * anmeldung_gruppe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('mehrere_personen')->default(false);
        });
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('anmeldung_gruppe', 36)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropIndex(['anmeldung_gruppe']);
            $table->dropColumn('anmeldung_gruppe');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('mehrere_personen');
        });
    }
};
