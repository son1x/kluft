<?php

use Illuminate\Database\Migrations\Migration;
use Instanzen\DvTrier\Seeders\Inhalt\DownloadsAbrechnungsformulare;

/**
 * Inhalts-Migration der Instanz dv-trier: Download-Seite unter
 * „Diözesanverband“ mit Fahrt-, Reise- und Sachkostenabrechnung.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new DownloadsAbrechnungsformulare)->run();
    }

    // Seite und Dateien bleiben: inzwischen könnten sie bearbeitet sein.
    public function down(): void {}
};
