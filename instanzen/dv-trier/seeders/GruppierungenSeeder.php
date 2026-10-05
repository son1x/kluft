<?php

namespace Instanzen\DvTrier\Seeders;

use App\Models\Gruppierung;
use Illuminate\Database\Seeder;

/**
 * Die zehn Bezirke des Diözesanverbands (Namen laut dpsg-trier.de) als
 * Ausgangsbestand. Stämme, Kontakte und Standorte werden bewusst nicht
 * erfunden, sondern bei den Bezirken erhoben (F-MAP-10). firstOrCreate
 * statt updateOrCreate: ein erneutes Seeden überschreibt keine im Backend
 * gepflegten Angaben.
 */
class GruppierungenSeeder extends Seeder
{
    public function run(): void
    {
        $bezirke = [
            'saar-hochwald' => ['name' => 'Saar-Hochwald', 'website_url' => 'https://www.dpsg-saar-hochwald.de'],
            'trier' => ['name' => 'Trier'],
            'eifel-ahr' => ['name' => 'Eifel-Ahr'],
            'mittelmosel' => ['name' => 'Mittelmosel'],
            'koblenz' => ['name' => 'Koblenz'],
            'rhein-hunsrueck-nahe' => ['name' => 'Rhein-Hunsrück-Nahe'],
            'rhein-wied' => ['name' => 'Rhein-Wied'],
            'sieg' => ['name' => 'Sieg'],
            'schaumberg-blies' => ['name' => 'Schaumberg-Blies'],
            'saarbruecken' => ['name' => 'Saarbrücken'],
        ];

        $sort = 0;
        foreach ($bezirke as $slug => $data) {
            Gruppierung::query()->firstOrCreate(
                ['slug' => $slug],
                [...$data, 'typ' => 'bezirk', 'active' => true, 'sort' => $sort += 10],
            );
        }
    }
}
