<?php

use Database\Seeders\DemoContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hauptmenüpunkt „Diözesanverband“ mit Diözesanleitung, Bezirke & Stämme,
 * Georgsbote (PDF-Zeitschrift) und Georgsbrief (Newsletter). Ändert nur
 * Seiten im Ausgangszustand; auf leerer Datenbank (frische Installation,
 * Tests) legt der DemoContentSeeder dieselbe Struktur an.
 */
return new class extends Migration
{
    private const OLD_GEORGSBOTE_BODY = '<p>Der Georgsbote ist die Zeitschrift des DPSG Diözesanverbands Trier mit Berichten aus Stufen, Bezirken und Stämmen. Hier findest du alle Ausgaben als PDF; über den Newsletter kommen neue Ausgaben direkt in dein Postfach.</p>';

    public function up(): void
    {
        if (! DB::table('pages')->where('slug', 'stufen')->exists()) {
            return;
        }

        $dv = DB::table('pages')->where('slug', 'dioezesanverband')->value('id')
            ?? $this->insertPage(['slug' => 'dioezesanverband', 'title' => 'Diözesanverband', 'body' => DemoContentSeeder::DV_BODY, 'show_in_nav' => true, 'nav_sort' => 10]);

        DB::table('pages')->where('slug', 'dioezesanleitung')->whereNull('parent_id')
            ->update(['parent_id' => $dv, 'show_in_nav' => true, 'nav_sort' => 10, 'updated_at' => now()]);

        foreach ([
            ['slug' => 'bezirke-staemme', 'title' => 'Bezirke & Stämme', 'nav_sort' => 20, 'seo_description' => 'Alle Bezirke und Stämme im Bistum Trier auf der Karte', 'body' => DemoContentSeeder::STAEMME_BODY],
            ['slug' => 'georgsbote', 'title' => 'Georgsbote', 'nav_sort' => 30, 'seo_description' => 'Die Zeitschrift des Diözesanverbands – alle Ausgaben als PDF', 'body' => DemoContentSeeder::GEORGSBOTE_BODY],
            ['slug' => 'georgsbrief', 'title' => 'Georgsbrief', 'nav_sort' => 40, 'seo_description' => 'Der Newsletter der DPSG Trier', 'body' => DemoContentSeeder::GEORGSBRIEF_BODY],
        ] as $page) {
            $existing = DB::table('pages')->where('slug', $page['slug'])->first();
            if (! $existing) {
                $this->insertPage([...$page, 'parent_id' => $dv, 'show_in_nav' => true]);

                continue;
            }
            if ($existing->parent_id === null) {
                $update = ['parent_id' => $dv, 'show_in_nav' => true, 'nav_sort' => $page['nav_sort'], 'updated_at' => now()];
                $update['seo_description'] = $existing->seo_description ?: $page['seo_description'];
                // Text nur ersetzen, wenn er noch der ursprüngliche ist.
                if ($page['slug'] === 'georgsbote' && trim((string) $existing->body) === self::OLD_GEORGSBOTE_BODY) {
                    $update['body'] = DemoContentSeeder::GEORGSBOTE_BODY;
                }
                DB::table('pages')->where('id', $existing->id)->update($update);
            }
        }
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

    public function down(): void
    {
        // Rein inhaltlich; nichts zurückzuholen.
    }
};
