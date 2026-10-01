<?php

use Database\Seeders\DemoContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Diözesanleitung (ehrenamtlich, führt den Verband) und Diözesanbüro
 * (hauptberufliche Unterstützung) als getrennte Seiten; das Büro hängt
 * unter "Kontakt". Inhalte werden nur ersetzt, wenn sie noch dem
 * ursprünglichen Beispieltext entsprechen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('pages')->where('slug', 'dioezesanleitung')->exists()) {
            DB::table('pages')->insert([
                'slug' => 'dioezesanleitung',
                'title' => 'Diözesanleitung',
                'body' => DemoContentSeeder::LEITUNG_BODY,
                'status' => 'published',
                'published_at' => $now,
                'show_in_nav' => true,
                'nav_sort' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $buero = DB::table('pages')->where('slug', 'dioezesanbuero')->first();
        if ($buero) {
            $update = [
                'show_in_nav' => false,
                'parent_id' => DB::table('pages')->where('slug', 'kontakt')->value('id'),
                'updated_at' => $now,
            ];

            $unveraendert = str_contains($buero->body, '<h2>Die Diözesanleitung</h2>')
                && str_contains($buero->body, '<strong>Diözesanvorstand:</strong> Till Mang')
                && str_contains($buero->body, '<h2>Struktur des Diözesanverbands</h2>');
            if ($unveraendert) {
                $update['body'] = DemoContentSeeder::BUERO_BODY;
            }

            DB::table('pages')->where('id', $buero->id)->update($update);
        }

        $kontakt = DB::table('pages')->where('slug', 'kontakt')->first();
        $alt = 'das Leitungsteam auf der Seite <a href="/dioezesanbuero">Diözesanbüro</a>.';
        if ($kontakt && str_contains($kontakt->body, $alt)) {
            DB::table('pages')->where('id', $kontakt->id)->update([
                'body' => str_replace(
                    ', '.$alt,
                    '. Die hauptberuflichen Ansprechpersonen findest du im <a href="/dioezesanbuero">Diözesanbüro</a>, das ehrenamtliche Leitungsteam unter <a href="/dioezesanleitung">Diözesanleitung</a>.',
                    $kontakt->body
                ),
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
