<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use App\Models\Person;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Jede*r in Diözesanleitung und -büro ist unter Initial.Nachname@
 * dpsg-trier.de erreichbar: fehlende Adressen eintragen (vorhandene
 * bleiben). DAK-Mitglieder haben keine eigene Adresse.
 *
 * Früher Migration 2026_10_02_120000_fill_person_email_addresses.
 */
class FillPersonEmailAddresses extends Seeder
{
    public function run(): void
    {
        foreach (DB::table('people')->where('gremium', '!=', 'dak')->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->get(['id', 'name']) as $person) {
            if ($email = Person::suggestedEmail($person->name)) {
                DB::table('people')->where('id', $person->id)->update(['email' => $email, 'updated_at' => now()]);
            }
        }

        DB::table('people')->where('gremium', 'dak')->whereNotNull('email')->update(['email' => null, 'updated_at' => now()]);
    }
}
