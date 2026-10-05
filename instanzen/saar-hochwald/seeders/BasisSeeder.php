<?php

namespace Instanzen\SaarHochwald\Seeders;

use Illuminate\Database\Seeder;

/**
 * Grundbestand des Bezirks (Bezirk und Stämme, Glossar).
 * Läuft auch in den Tests (config dpsg.seeders.basis).
 */
class BasisSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([GruppierungenSeeder::class, StaemmeSeeder::class, GlossarSeeder::class]);
    }
}
