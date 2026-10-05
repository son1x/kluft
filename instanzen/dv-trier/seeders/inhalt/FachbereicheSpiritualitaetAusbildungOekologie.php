<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fachbereiche Spiritualität, Ausbildung und Ökologie (je mit
 * Facharbeitskreis): Startseite unter „Fachbereiche“ und Thema im
 * Kontaktformular. Legt nur an, was fehlt; ohne Seite „fachbereiche“
 * (frische Installation vor dem Seeden) passiert nichts – dort legt der
 * DemoContentSeeder die Seiten an, die Kontaktthemen kommen aus der
 * Konfiguration.
 */
class FachbereicheSpiritualitaetAusbildungOekologie extends Seeder
{
    public const SEITEN = [
        ['slug' => 'spiritualitaet', 'title' => 'Spiritualität', 'nav_sort' => 40,
            'seo_description' => 'Glauben leben – in der Gruppenstunde, auf dem Lager, im Verband'],
        ['slug' => 'ausbildung', 'title' => 'Ausbildung', 'nav_sort' => 50,
            'seo_description' => 'Ausbildung für Leiter*innen: Modulausbildung, Woodbadge, Prävention'],
        ['slug' => 'oekologie', 'title' => 'Ökologie', 'nav_sort' => 60,
            'seo_description' => 'Natur erleben, Schöpfung bewahren, nachhaltig handeln'],
    ];

    public const SPIRITUALITAET_BODY = <<<'HTML'
        <p>Glaube und Spiritualität gehören zum Pfadfinden in der DPSG: beim Morgenimpuls auf dem Lager, im Gottesdienst unter freiem Himmel, in der Gruppenstunde oder beim Friedenslicht. Der Fachbereich Spiritualität unterstützt Stämme, Bezirke und Leiter*innen dabei, Glauben lebendig und für alle Altersstufen erlebbar zu machen.</p>
        <p>Er entwickelt Impulse und Material, begleitet Kurat*innen und gestaltet geistliche Angebote im Diözesanverband.</p>
        HTML;

    public const AUSBILDUNG_BODY = <<<'HTML'
        <p>Gute Gruppenstunden brauchen gut ausgebildete Leiter*innen. Der Fachbereich Ausbildung organisiert im Diözesanverband die Ausbildung für Leitende: von der Modulausbildung über die Präventionsschulung bis zum Woodbadge-Kurs.</p>
        <p>Er begleitet Teamer*innen, entwickelt die Kurse weiter und berät Stämme und Bezirke rund um Ausbildung und Juleica.</p>
        HTML;

    public const OEKOLOGIE_BODY = <<<'HTML'
        <p>Draußen sein, Natur erleben und die Schöpfung bewahren: Ökologie ist Teil des pfadfinderischen Alltags. Der Fachbereich Ökologie gibt Anregungen für nachhaltige Lager und Gruppenstunden, für Naturerfahrung und für Aktionen rund um Klima- und Umweltschutz.</p>
        <p>Er unterstützt Stämme, Bezirke und Leiter*innen mit Ideen, Material und Fortbildungen.</p>
        HTML;

    public static function body(string $slug): string
    {
        return match ($slug) {
            'spiritualitaet' => self::SPIRITUALITAET_BODY,
            'ausbildung' => self::AUSBILDUNG_BODY,
            default => self::OEKOLOGIE_BODY,
        };
    }

    public function run(): void
    {
        $fachbereiche = DB::table('pages')->where('slug', 'fachbereiche')->value('id');
        if (! $fachbereiche) {
            return;
        }

        foreach (static::SEITEN as $seite) {
            if (! DB::table('pages')->where('slug', $seite['slug'])->exists()) {
                DB::table('pages')->insert([
                    ...$seite,
                    'body' => static::body($seite['slug']),
                    'section' => $seite['slug'],
                    'parent_id' => $fachbereiche,
                    'show_in_nav' => true,
                    'status' => 'published',
                    'published_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Kontaktthemen nur ergänzen, wenn das Formular schon Themen hat.
        if (DB::table('contact_topics')->exists()) {
            $sort = (int) DB::table('contact_topics')->max('sort');
            foreach (static::SEITEN as $seite) {
                if (! DB::table('contact_topics')->where('slug', $seite['slug'])->exists()) {
                    DB::table('contact_topics')->insert([
                        'label' => 'Fachbereich: '.$seite['title'], 'slug' => $seite['slug'], 'section' => $seite['slug'],
                        'stage' => null, 'email' => null, 'sort' => $sort += 10, 'active' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
