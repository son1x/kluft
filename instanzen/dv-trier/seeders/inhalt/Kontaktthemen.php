<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Startthemen des Kontaktformulars und Formular statt Platzhalter auf
 * der Kontaktseite (nur außerhalb der Tests).
 *
 * Früher Teil der Migration 2026_10_02_180000_create_contact_topics_table.
 */
class Kontaktthemen extends Seeder
{
    public function run(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $topics = [['label' => 'Allgemeine Frage an das Diözesanbüro', 'slug' => 'allgemein']];
        $topics[] = ['label' => 'Mitmachen: Stamm in meiner Nähe', 'slug' => 'mitmachen'];
        foreach (config('dpsg.stages') as $key => $stage) {
            $topics[] = ['label' => 'Stufe: '.$stage['label'], 'slug' => $key, 'stage' => $key];
        }
        foreach (config('dpsg.sections') as $key => $section) {
            $topics[] = ['label' => 'Fachbereich: '.$section['label'], 'slug' => $key, 'section' => $key];
        }
        $topics[] = ['label' => 'Georgsbrief und Georgsbote', 'slug' => 'georgsbrief'];
        $topics[] = ['label' => 'Website (Fehler, Hinweise)', 'slug' => 'website'];

        foreach ($topics as $i => $topic) {
            DB::table('contact_topics')->insert([
                'stage' => null, 'section' => null, 'email' => null, ...$topic,
                'sort' => ($i + 1) * 10, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Platzhalter auf der Kontaktseite durch das Formular ersetzen.
        $page = DB::table('pages')->where('slug', 'kontakt')->first(['id', 'body']);
        if ($page && str_contains($page->body, 'Kontaktformular folgt')) {
            DB::table('pages')->where('id', $page->id)->update([
                'body' => preg_replace('#\s*<p>\[TODO: Kontaktformular folgt[^<]*</p>#u', '', $page->body),
            ]);
        }
    }
}
