<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Instanzen\DvTrier\Seeders\Inhalt\BereichPraevention;

/**
 * Inhalts-Migration der Instanz dv-trier: Die Bestätigung für das
 * erweiterte Führungszeugnis erstellen Leitende über ihren NaMi-Zugang,
 * nicht das Diözesanbüro. Ändert die Seite nur, wenn der alte Satz noch
 * unverändert drinsteht.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pages')->where('slug', 'praevention')
            ->where('body', 'like', '%'.BereichPraevention::FUEHRUNGSZEUGNIS_ALT.'%')
            ->get(['id', 'body'])
            ->each(fn ($page) => DB::table('pages')->where('id', $page->id)->update([
                'body' => str_replace(BereichPraevention::FUEHRUNGSZEUGNIS_ALT, BereichPraevention::FUEHRUNGSZEUGNIS_NEU, (string) $page->body),
                'updated_at' => now(),
            ]));
    }

    public function down(): void {}
};
