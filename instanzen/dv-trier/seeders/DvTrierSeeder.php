<?php

namespace Instanzen\DvTrier\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Startinhalt einer neuen DV-Trier-Installation (php artisan db:seed beim
 * ersten Deploy): zuerst der frühere Migrationsinhalt, dann Pflichtseiten
 * und Demo-Inhalt – dieselbe Reihenfolge wie vor Kluft. Wie damals laufen
 * die früheren Migrationsschritte mit Model-Events (z. B. Bildvarianten),
 * Pflichtseiten und Demo-Inhalt ohne.
 */
class DvTrierSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(InhaltSeeder::class);
        Model::withoutEvents(fn () => $this->call([RequiredPagesSeeder::class, DemoContentSeeder::class]));
    }
}
