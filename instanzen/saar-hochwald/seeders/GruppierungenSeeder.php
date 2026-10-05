<?php

namespace Instanzen\SaarHochwald\Seeders;

use App\Models\Gruppierung;
use Illuminate\Database\Seeder;

/**
 * Der Bezirk Saar-Hochwald als Ausgangsbestand des Verzeichnisses. Ohne
 * Position: die setzt der StaemmeSeeder bzw. die Verwaltung im Backend.
 * firstOrCreate statt updateOrCreate: ein erneutes Seeden überschreibt
 * keine im Backend gepflegten Angaben.
 */
class GruppierungenSeeder extends Seeder
{
    public function run(): void
    {
        Gruppierung::query()->firstOrCreate(
            ['slug' => 'saar-hochwald'],
            [
                'name' => 'Saar-Hochwald',
                'typ' => 'bezirk',
                'website_url' => 'https://www.dpsg-saar-hochwald.de',
                'kontakt_email' => 'bezirksleitung@dpsg-saar-hochwald.de',
                'active' => true,
                'sort' => 10,
            ],
        );
    }
}
