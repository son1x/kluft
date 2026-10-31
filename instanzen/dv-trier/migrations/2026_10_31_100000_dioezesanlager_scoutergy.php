<?php

use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Rubrik „Diözesanlager“ mit dem Archiv „Scoutergy“ (FroLeiLa 2023,
 * Inhalte aus son1x/wp-scoutergy, umgewandelt mit
 * dateien/scoutergy/konverter.py): Übersichtsseite unter
 * „Diözesanverband“, vier Seiten, sechs Beiträge mit den alten Daten und
 * die Bilder. Nur dv-trier, nach dem Startinhalt. Vorhandenes (gleicher
 * Slug bzw. gleiche Datei) bleibt unberührt, im Backend änderbar.
 */
return new class extends Migration
{
    private const SECTION = 'scoutergy';

    public function up(): void
    {
        if (config('dpsg.instanz') !== 'dv-trier' || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }

        $quelle = base_path('instanzen/dv-trier/dateien/scoutergy');
        $inhalt = json_decode((string) File::get($quelle.'/inhalt.json'), true, flags: JSON_THROW_ON_ERROR);
        $now = now();

        foreach ($inhalt['medien'] as $m) {
            $this->medium($quelle.'/medien/'.$m['datei'], $m);
        }

        // Übersicht „Diözesanlager“ als Unterpunkt von „Diözesanverband“.
        $u = $inhalt['uebersicht'];
        if (! DB::table('pages')->where('slug', $u['slug'])->exists()) {
            $parent = DB::table('pages')->where('slug', 'dioezesanverband')->value('id');
            DB::table('pages')->insert([
                'parent_id' => $parent,
                'slug' => $u['slug'],
                'title' => $u['title'],
                'body' => $u['body'],
                'seo_description' => $u['seo_description'],
                'status' => 'published',
                'published_at' => $now,
                'show_in_nav' => $parent !== null,
                'nav_sort' => $parent ? (int) DB::table('pages')->where('parent_id', $parent)->max('nav_sort') + 10 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($inhalt['seiten'] as $s) {
            if (DB::table('pages')->where('slug', $s['slug'])->exists()) {
                continue;
            }
            DB::table('pages')->insert([
                'slug' => $s['slug'],
                'title' => $s['title'],
                'body' => $s['body'],
                'seo_description' => $s['seo_description'],
                'section' => self::SECTION,
                'status' => 'published',
                'published_at' => $now,
                'show_in_nav' => false,
                'nav_sort' => $s['nav_sort'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($inhalt['beitraege'] as $b) {
            if (DB::table('posts')->where('slug', $b['slug'])->exists()) {
                continue;
            }
            DB::table('posts')->insert([
                'scope' => 'section',
                'section' => self::SECTION,
                'title' => $b['title'],
                'slug' => $b['slug'],
                'teaser' => $b['teaser'],
                'body' => $b['body'],
                'status' => 'published',
                'published_at' => $b['published_at'],
                'created_at' => $b['published_at'],
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array{datei: string, mime: string, alt: string, quelle: string}  $m
     */
    private function medium(string $datei, array $m): void
    {
        $pfad = 'media/'.$m['datei'];
        if (! File::exists($datei) || DB::table('media')->where('path', $pfad)->exists()) {
            return;
        }
        File::ensureDirectoryExists(public_path('uploads/media'));
        File::copy($datei, public_path('uploads/'.$pfad));

        Media::query()->create([
            'path' => $pfad,
            'mime' => $m['mime'],
            'size' => File::size($datei),
            'alt_text' => $m['alt'],
            'credit' => 'DPSG DV Trier (scoutergy.de)',
            'rights_confirmed' => true,
            'source_url' => $m['quelle'],
        ]);
    }

    public function down(): void
    {
        // Inhalte bleiben stehen; im Backend änderbar.
    }
};
