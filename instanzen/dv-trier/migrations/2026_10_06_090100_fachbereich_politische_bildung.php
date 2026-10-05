<?php

use Illuminate\Database\Migrations\Migration;
use Instanzen\DvTrier\Seeders\Inhalt\FachbereichPolitischeBildung;

/**
 * Inhalts-Migration der Instanz dv-trier: legt auf der bestehenden
 * Installation den Fachbereich Politische Bildung an.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new FachbereichPolitischeBildung)->run();
    }

    // Seite bleibt stehen: inzwischen könnte sie redaktionell bearbeitet sein.
    public function down(): void {}
};
