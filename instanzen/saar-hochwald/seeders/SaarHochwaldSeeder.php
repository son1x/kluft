<?php

namespace Instanzen\SaarHochwald\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Startinhalt einer neuen Installation des Bezirks Saar-Hochwald
 * (php artisan db:seed beim ersten Deploy): Grundbestand, dann
 * Pflichtseiten und Startseiten aus der bisherigen statischen Seite
 * samt Weiterleitungen der alten .html-Adressen; zuletzt die Themen des
 * Kontaktformulars (ersetzen den Platzhalter auf der Kontaktseite).
 */
class SaarHochwaldSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BasisSeeder::class);
        Model::withoutEvents(fn () => $this->call([RequiredPagesSeeder::class, DemoContentSeeder::class]));
        $this->call(KontaktthemenSeeder::class);
    }
}
