<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Beispielveranstaltungen für die Testumgebung, damit Übersicht,
 * Detailseite und Anmeldung ausprobiert werden können. Nur wenn noch
 * keine Veranstaltung existiert und die Demo-Startseite angelegt ist.
 *
 * Früher Migration 2026_10_01_231348_seed_demo_events.
 */
class SeedDemoEvents extends Seeder
{
    public function run(): void
    {
        if (DB::table('events')->exists() || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }

        $hinweis = '<p><em>Beispielveranstaltung zum Ausprobieren der Online-Anmeldung.</em></p>';
        $now = now();

        $events = [
            [
                'title' => 'Diözesan-Wölflingstag 2027',
                'slug' => 'woelflingstag-2027',
                'teaser' => 'Ein Tag voller Spiele, Abenteuer und neuer Freundschaften für alle Wölflinge im Diözesanverband.',
                'description' => $hinweis.'<p>Einmal im Jahr treffen sich die Wölflingsmeuten aus dem ganzen Bistum. Euch erwarten ein großes Geländespiel, Workshops zum Mitmachen und ein gemeinsamer Abschluss.</p><h3>Mitbringen</h3><ul><li>Kluft oder Halstuch</li><li>wetterfeste Kleidung</li><li>Trinkflasche</li></ul>',
                'starts_at' => '2027-06-12 10:00:00',
                'ends_at' => '2027-06-12 17:00:00',
                'location' => 'Jugendhaus Marienburg, Zell (Mosel)',
                'stage' => 'woelflinge',
                'target_group' => 'Wölflinge (7–10 Jahre) mit ihren Leitenden',
                'contact_name' => 'Diözesanarbeitskreis Wölflinge',
                'contact_email' => 'info@dpsg-trier.de',
                'cost_info' => '8 € pro Kind inkl. Mittagessen',
                'reg_closes_at' => '2027-05-28 23:59:00',
                'capacity' => 120,
                'waitlist_enabled' => true,
                'status' => 'open',
                'form_fields' => json_encode(['birth_year' => 'required', 'stamm' => 'required', 'diet' => 'optional', 'comment' => 'optional']),
            ],
            [
                'title' => 'Modulausbildung für Leitende – Frühjahr 2027',
                'slug' => 'modulausbildung-fruehjahr-2027',
                'teaser' => 'Grundlagen der Gruppenleitung: Pädagogik, Aufsicht, Prävention und Methoden für die Gruppenstunde.',
                'description' => $hinweis.'<p>Das Wochenende ist Teil der Woodbadge-Ausbildung und richtet sich an alle, die neu in der Leitung sind oder ihr Wissen auffrischen möchten.</p><p>Die Präventionsschulung ist enthalten.</p>',
                'starts_at' => '2027-03-19 18:00:00',
                'ends_at' => '2027-03-21 14:00:00',
                'location' => 'Jugendhaus Marienburg, Zell (Mosel)',
                'stage' => null,
                'target_group' => 'Leitende aller Stufen ab 18 Jahren',
                'contact_name' => 'Diözesanbüro',
                'contact_email' => 'info@dpsg-trier.de',
                'cost_info' => '60 € inkl. Unterkunft und Verpflegung (Zuschuss über den Stamm möglich)',
                'reg_closes_at' => '2027-03-01 23:59:00',
                'capacity' => 24,
                'waitlist_enabled' => true,
                'status' => 'open',
                'form_fields' => json_encode(['birth_year' => 'required', 'stamm' => 'required', 'phone' => 'optional', 'diet' => 'required', 'comment' => 'optional']),
            ],
            [
                'title' => 'Rover-Hike auf dem Eifelsteig',
                'slug' => 'rover-hike-eifelsteig-2027',
                'teaser' => 'Drei Tage, 60 Kilometer, ein Rucksack – unterwegs auf dem Eifelsteig mit Rovern aus der ganzen Diözese.',
                'description' => $hinweis.'<p>Wir wandern in Kleingruppen, übernachten unter freiem Himmel und kochen selbst. Kondition für ca. 20 km am Tag solltest du mitbringen.</p>',
                'starts_at' => '2027-05-07 16:00:00',
                'ends_at' => '2027-05-09 15:00:00',
                'location' => 'Start: Gerolstein',
                'stage' => 'rover',
                'target_group' => 'Rover (16–20 Jahre)',
                'contact_name' => 'Diözesanarbeitskreis Rover',
                'contact_email' => 'info@dpsg-trier.de',
                'cost_info' => '35 €',
                'reg_opens_at' => '2027-01-15 18:00:00',
                'reg_closes_at' => '2027-04-15 23:59:00',
                'capacity' => 16,
                'waitlist_enabled' => false,
                'status' => 'open',
                'form_fields' => json_encode(['birth_year' => 'required', 'stamm' => 'optional', 'phone' => 'required', 'diet' => 'optional']),
            ],
        ];

        foreach ($events as $event) {
            DB::table('events')->insert([...$event, 'retention_days' => 90, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
