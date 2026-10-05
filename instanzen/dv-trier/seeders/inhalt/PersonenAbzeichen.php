<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gestaltung der Initialen-Kreise und Sektion der Ansprechperson Bolivien.
 *
 * Früher Teil der Migration 2026_10_02_080246_add_badge_and_section_to_people.
 */
class PersonenAbzeichen extends Seeder
{
    public function run(): void
    {
        DB::table('people')->whereNull('badge')->whereNotNull('stage')->update(['badge' => DB::raw('stage')]);
        DB::table('people')->where('name', 'Bianca Boltze')->whereNull('badge')->update(['badge' => 'inklusion']);
        DB::table('people')->where('name', 'Jannis Odrosek')->update(['badge' => 'bolivien', 'section' => 'bolivien']);
    }
}
