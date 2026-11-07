<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Instanzen\DvTrier\Seeders\RequiredPagesSeeder;

/*
| Datenschutzerklärung: die mit dem Betrieblichen Datenschutz abgestimmte
| Fassung von dpsg-trier.de als Grundlage, ergänzt um die Verarbeitungen der
| neuen Website (dateien/datenschutz.html). Ersetzt nur den Entwurf aus der
| Einrichtung; eine im Backend schon bearbeitete Seite bleibt unverändert.
*/
return new class extends Migration
{
    public function up(): void
    {
        $seite = Page::query()->where('slug', 'datenschutz')->first();
        if (! $seite || ! str_contains((string) $seite->body, 'orientiert sich an den Angaben von dpsg-trier.de, ersetzt keine Rechtsberatung')) {
            return;
        }

        $seite->forceFill(['body' => RequiredPagesSeeder::datenschutz()])->save();
    }

    public function down(): void
    {
        // Inhalt bleibt.
    }
};
