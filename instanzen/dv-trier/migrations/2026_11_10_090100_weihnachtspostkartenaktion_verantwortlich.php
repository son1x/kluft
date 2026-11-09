<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Weihnachtspostkartenaktion: verantwortlich ist die Bildungsreferentin
 * Lisa Alm (Auftrag Projektleitung 10.11.2026). Adresse aus ihrem Eintrag
 * unter Leitung & Büro, sonst nach dem Schema der Dienstadressen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $person = DB::table('people')->where('name', 'Lisa Alm')->first();
        $adresse = ($person->email ?? null) ?: Person::suggestedEmail('Lisa Alm');

        DB::table('events')->where('slug', 'weihnachtspostkartenaktion-2026')->whereNull('verantwortlich_email')
            ->update(['verantwortlich_email' => $adresse, 'info_sammeladresse' => true, 'info_verantwortlich' => true]);
    }

    public function down(): void
    {
        // Bleibt; im Backend änderbar.
    }
};
