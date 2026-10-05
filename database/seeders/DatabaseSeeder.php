<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Startinhalt der aktiven Kluft-Instanz (config dpsg.seeders.start, z. B.
 * instanzen/dv-trier/seeders/DvTrierSeeder.php). Ohne Eintrag nichts.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if ($start = config('dpsg.seeders.start')) {
            $this->call($start);
        }
    }
}
