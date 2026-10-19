<?php

use App\Support\Leitergewinnung;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seite „Leiter*in werden“ im Menü (statt des Aufrufs auf jeder Stufenseite)
 * für die schon laufende Roden-Website. Inhalt gehört nicht nach
 * database/migrations (docs/entscheidungen/kluft-inhalt-und-schema.md); neue
 * Stämme bekommen die Seite vom StammStartSeeder. Vorhandenes bleibt unberührt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Leitergewinnung::aufStammesebene()
            || ! DB::table('pages')->where('slug', 'start')->exists()
            || DB::table('pages')->where('slug', Leitergewinnung::SLUG)->exists()) {
            return;
        }

        DB::table('pages')->insert([
            'slug' => Leitergewinnung::SLUG,
            'title' => Leitergewinnung::TITEL,
            'body' => Leitergewinnung::text(),
            'status' => 'published',
            'published_at' => now(),
            'seo_description' => 'Ehrenamtlich Gruppen leiten im Stamm',
            'show_in_nav' => true,
            'nav_sort' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Inhalte bleiben stehen; die Seite lässt sich im Backend löschen.
    }
};
