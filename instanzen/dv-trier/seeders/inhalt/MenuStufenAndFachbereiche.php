<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Instanzen\DvTrier\Seeders\DemoContentSeeder;

/**
 * Hauptmenü mit Unterpunkten: Die Stufen › Biber … Rover und (neu)
 * Fachbereiche › Bolivienpartnerschaft, Internationale Gerechtigkeit,
 * Inklusion. Ändert nur, was noch im Ausgangszustand ist (Seite ohne
 * übergeordnete Seite bzw. noch nicht vorhanden); auf einer leeren
 * Datenbank (frische Installation, Tests) passiert nichts – dort legt der
 * DemoContentSeeder dieselbe Struktur an.
 *
 * Früher Migration 2026_10_02_110000_menu_stufen_and_fachbereiche.
 */
class MenuStufenAndFachbereiche extends Seeder
{
    public function run(): void
    {
        $stufen = DB::table('pages')->where('slug', 'stufen')->first();
        if (! $stufen) {
            return;
        }

        foreach (['biber', 'woelflinge', 'jupfi', 'pfadi', 'rover'] as $i => $stage) {
            DB::table('pages')->where('stage', $stage)->whereNull('parent_id')->where('slug', "stufe-{$stage}")
                ->update(['parent_id' => $stufen->id, 'show_in_nav' => true, 'nav_sort' => ($i + 1) * 10, 'updated_at' => now()]);
        }

        $fachbereiche = DB::table('pages')->where('slug', 'fachbereiche')->value('id')
            ?? $this->insertPage([
                'slug' => 'fachbereiche', 'title' => 'Fachbereiche', 'body' => DemoContentSeeder::FACHBEREICHE_BODY,
                'show_in_nav' => true, 'nav_sort' => 30,
            ]);

        DB::table('pages')->where('slug', 'bolivien')->whereNull('parent_id')
            ->update(['parent_id' => $fachbereiche, 'nav_sort' => 10, 'updated_at' => now()]);

        foreach ([
            ['slug' => 'internationale-gerechtigkeit', 'title' => 'Internationale Gerechtigkeit', 'nav_sort' => 20,
                'seo_description' => 'Global denken, gemeinsam handeln', 'body' => DemoContentSeeder::GERECHTIGKEIT_BODY],
            ['slug' => 'inklusion', 'title' => 'Inklusion', 'nav_sort' => 30,
                'seo_description' => 'Pfadfinden für alle – Ich, Du, Wir', 'body' => DemoContentSeeder::INKLUSION_BODY],
        ] as $page) {
            if (! DB::table('pages')->where('slug', $page['slug'])->exists()) {
                $this->insertPage([...$page, 'section' => $page['slug'], 'parent_id' => $fachbereiche, 'show_in_nav' => true]);
            }
        }

        // Referentin „Inklusion / Ich-Du-Wir“ wird Ansprechperson des Fachbereichs.
        DB::table('people')->where('badge', 'inklusion')->whereNull('section')
            ->update(['section' => 'inklusion', 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insertPage(array $data): int
    {
        return (int) DB::table('pages')->insertGetId([
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            ...$data,
        ]);
    }
}
