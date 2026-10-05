<?php

use Illuminate\Database\Migrations\Migration;
use Instanzen\DvTrier\Seeders\Inhalt\FachbereicheSpiritualitaetAusbildungOekologie;

/**
 * Inhalts-Migration der Instanz dv-trier (läuft nur dort, siehe
 * AppServiceProvider): legt auf der bestehenden Installation die
 * Fachbereiche Spiritualität, Ausbildung und Ökologie an.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new FachbereicheSpiritualitaetAusbildungOekologie)->run();
    }

    // Seiten bleiben stehen: inzwischen könnten sie redaktionell bearbeitet sein.
    public function down(): void {}
};
