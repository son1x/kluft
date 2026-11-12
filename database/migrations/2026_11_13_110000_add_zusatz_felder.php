<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zusatzinfos fürs Team (docs/vor-ort.md): Felder je Veranstaltung wie
 * „Zimmer“ oder „Zelt“, die nicht im Anmeldeformular stehen; die Werte
 * trägt das Team je Anmeldung ein.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->json('zusatz_felder')->nullable());
        Schema::table('event_registrations', fn (Blueprint $t) => $t->json('zusatz')->nullable());
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('zusatz_felder'));
        Schema::table('event_registrations', fn (Blueprint $t) => $t->dropColumn('zusatz'));
    }
};
