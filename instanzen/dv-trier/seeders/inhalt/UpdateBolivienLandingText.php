<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bolivien-Startseite: Partner und Leitgedanke „Amistad – Freundschaft“
 * nennen; Ansprechperson kommt jetzt aus der Personenverwaltung, Spenden
 * werden nur noch dezent verlinkt. Nur wenn der Text unverändert ist.
 *
 * Früher Migration 2026_10_02_080349_update_bolivien_landing_text.
 */
class UpdateBolivienLandingText extends Seeder
{
    public function run(): void
    {
        $page = DB::table('pages')->where('slug', 'bolivien')->first();

        if (! $page || ! str_contains((string) $page->body, '[TODO: Aktuelle Projekte, Ansprechperson und Spendenmöglichkeiten ergänzen.]')) {
            return;
        }

        DB::table('pages')->where('id', $page->id)->update([
            'body' => <<<'HTML'
                <p><strong>Amistad – Freundschaft:</strong> Unter diesem Leitgedanken verbindet der DPSG Diözesanverband Trier eine Partnerschaft mit den Pfadfinder*innen des Distrikts Cochabamba der Asociación de Scouts de Bolivia (ASB).</p>
                <p>Eingebettet in die Bolivienpartnerschaft des Bistums Trier leben Begegnungsreisen, gemeinsame Projekte und der Austausch über Ländergrenzen hinweg – so wird Pfadfinden als weltweite Bewegung erlebbar.</p>
                <p>[TODO: Aktuelle Projekte und Begegnungen ergänzen.]</p>
                HTML,
            'updated_at' => now(),
        ]);
    }
}
