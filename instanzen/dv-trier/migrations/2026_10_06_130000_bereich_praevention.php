<?php

use Illuminate\Database\Migrations\Migration;
use Instanzen\DvTrier\Seeders\Inhalt\BereichPraevention;

/**
 * Inhalts-Migration der Instanz dv-trier: Bereich Prävention unter
 * „Diözesanverband“ mit Ansprechperson und Kontaktthema.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new BereichPraevention)->run();
    }

    // Seite bleibt stehen: inzwischen könnte sie redaktionell bearbeitet sein.
    public function down(): void {}
};
