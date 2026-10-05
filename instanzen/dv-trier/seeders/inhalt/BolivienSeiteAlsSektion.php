<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seite „bolivien“ der Sektion zuordnen.
 *
 * Früher Teil der Migration 2026_10_01_225318_add_section_to_pages_and_posts.
 */
class BolivienSeiteAlsSektion extends Seeder
{
    public function run(): void
    {
        DB::table('pages')->where('slug', 'bolivien')->update(['section' => 'bolivien']);
    }
}
