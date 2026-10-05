<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Einleitung der Georgsbote-Seite an das jetzt vorhandene Archiv anpassen –
 * nur, wenn der ursprüngliche Beispieltext noch unverändert ist.
 *
 * Früher Migration 2026_10_01_225157_update_georgsbote_intro_text.
 */
class UpdateGeorgsboteIntroText extends Seeder
{
    public function run(): void
    {
        $page = DB::table('pages')->where('slug', 'georgsbote')->first();

        if ($page && str_contains($page->body, 'Künftig findest du hier alle Ausgaben im PDF-Archiv und kannst dich für den Newsletter anmelden, um neue Ausgaben direkt ins Postfach zu bekommen.')) {
            $body = str_replace('Künftig findest du hier alle Ausgaben im PDF-Archiv und kannst dich für den Newsletter anmelden, um neue Ausgaben direkt ins Postfach zu bekommen.', 'Hier findest du alle Ausgaben als PDF; über den Newsletter kommen neue Ausgaben direkt in dein Postfach.', $page->body);
            $body = preg_replace('#\s*<p>\[TODO: Archiv und Newsletter-Anmeldung folgen \(Pflichtenheft 6\.6\)\.\]</p>#', '', $body);
            DB::table('pages')->where('id', $page->id)->update(['body' => $body, 'updated_at' => now()]);
        }
    }
}
