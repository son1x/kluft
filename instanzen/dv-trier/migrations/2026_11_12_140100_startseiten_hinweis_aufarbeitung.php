<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Startseiten-Hinweis auf die Aufarbeitung sexualisierter und spiritueller
 * Gewalt in der DPSG (Seite des Bundesverbands). Danach im Backend unter Inhalte → Startseiten-
 * Hinweise bearbeitbar; läuft die Migration erneut, bleibt er unberührt.
 */
return new class extends Migration
{
    private const LINK = 'https://dpsg.de/de/verbandsleben/themen/kinder-und-jugendschutz/aufarbeitung';

    /** In Tests nur auf ausdrücklichen Wunsch (andere Tests erwarten keine Hinweise). */
    public bool $auchInTests = false;

    public function up(): void
    {
        if ((app()->runningUnitTests() && ! $this->auchInTests) || DB::table('startseiten_hinweise')->where('link_url', self::LINK)->exists()) {
            return;
        }

        DB::table('startseiten_hinweise')->insert([
            // Formulierungen nach der Seite des Bundesverbands (Stand 12.11.2026).
            'titel' => 'Aufarbeitung sexualisierter und spiritueller Gewalt in der DPSG',
            'text' => 'Es gab und gibt sexualisierte und spirituelle Gewalt in der DPSG. Dafür übernehmen wir Verantwortung. Ergebnisse der Aufarbeitungsstudie, Ansprechpersonen und Hilfsangebote findest du beim Bundesverband. Hilfe-Telefon Sexueller Missbrauch: 0800 22 55 530 (anonym und kostenfrei).',
            'link_url' => self::LINK,
            'link_text' => 'Zur Aufarbeitung auf dpsg.de',
            'farbe' => 'rot',
            'aktiv' => true,
            'sort' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('startseiten_hinweise')->where('link_url', self::LINK)->delete();
    }
};
