<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Till Mang ist Diözesanvorsitzender (nicht „Diözesanvorstand“ – das ist
 * das Gremium). Nur ändern, wenn noch der alte Wert eingetragen ist.
 *
 * Früher Migration 2026_10_02_160000_fix_till_mang_role.
 */
class FixTillMangRole extends Seeder
{
    public function run(): void
    {
        DB::table('people')->where('name', 'Till Mang')->where('role', 'Diözesanvorstand')
            ->update(['role' => 'Diözesanvorsitzender', 'updated_at' => now()]);
    }
}
