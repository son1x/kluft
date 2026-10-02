<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jede*r in Diözesanleitung und -büro ist unter Initial.Nachname@
 * dpsg-trier.de erreichbar: fehlende Adressen eintragen (vorhandene
 * bleiben). DAK-Mitglieder haben keine eigene Adresse.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('people')->where('gremium', '!=', 'dak')->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->get(['id', 'name']) as $person) {
            if ($email = Person::suggestedEmail($person->name)) {
                DB::table('people')->where('id', $person->id)->update(['email' => $email, 'updated_at' => now()]);
            }
        }

        DB::table('people')->where('gremium', 'dak')->whereNotNull('email')->update(['email' => null, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Rein inhaltlich; nichts zurückzuholen.
    }
};
