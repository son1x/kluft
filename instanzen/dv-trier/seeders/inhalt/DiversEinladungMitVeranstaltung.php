<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Einladung zur Diözesanversammlung mit der Veranstaltung verknüpfen.
 *
 * Früher Teil der Migration 2026_10_03_120000_add_event_id_to_posts.
 */
class DiversEinladungMitVeranstaltung extends Seeder
{
    private const DIVERS_LINK = '<p><a href="/veranstaltungen/dioezesanversammlung-2026"><strong>Hier geht’s zur Anmeldung</strong></a> – Anmeldeschluss ist der 8. November 2026.</p>';

    public function run(): void
    {
        $event = DB::table('events')->where('slug', 'dioezesanversammlung-2026')->value('id');
        $post = DB::table('posts')->where('slug', 'einladung-zur-91-dioezesanversammlung')->first(['id', 'body', 'event_id']);
        if ($event && $post && ! $post->event_id) {
            DB::table('posts')->where('id', $post->id)->update([
                'event_id' => $event,
                'body' => preg_replace('#\s*'.preg_quote(self::DIVERS_LINK, '#').'#u', '', $post->body),
                'updated_at' => now(),
            ]);
        }
    }
}
