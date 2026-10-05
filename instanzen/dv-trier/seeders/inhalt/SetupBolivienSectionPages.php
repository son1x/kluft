<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Instanzen\DvTrier\Seeders\DemoContentSeeder;

/**
 * Bolivien-Sektion: Landingpage in die Hauptnavigation, Unterseite
 * "Spenden" anlegen (falls noch nicht vorhanden). Bestehende Inhalte
 * bleiben unverändert.
 *
 * Früher Migration 2026_10_01_225521_setup_bolivien_section_pages.
 */
class SetupBolivienSectionPages extends Seeder
{
    public function run(): void
    {
        $landing = DB::table('pages')->where('slug', 'bolivien')->first();
        if (! $landing) {
            return;
        }

        DB::table('pages')->where('id', $landing->id)->update([
            'section' => 'bolivien',
            'show_in_nav' => true,
            'nav_sort' => 40,
            'seo_description' => $landing->seo_description ?? 'Partnerschaft mit Pfadfinder*innen in Bolivien',
            'body' => str_replace(' – eigene Landingpage folgt mit der Bolivien-Sektion (Pflichtenheft 6.2).', '.', $landing->body),
            'updated_at' => now(),
        ]);

        if (! DB::table('pages')->where('slug', 'bolivien-spenden')->exists()) {
            DB::table('pages')->insert([
                'parent_id' => $landing->id,
                'section' => 'bolivien',
                'slug' => 'bolivien-spenden',
                'title' => 'Spenden',
                'body' => DemoContentSeeder::SPENDEN_BODY,
                'status' => 'published',
                'published_at' => now(),
                'nav_sort' => 90,
                'show_in_nav' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
