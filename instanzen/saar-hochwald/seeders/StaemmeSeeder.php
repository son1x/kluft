<?php

namespace Instanzen\SaarHochwald\Seeders;

use App\Models\Gruppierung;
use Illuminate\Database\Seeder;

/**
 * Die fünf Stämme des Bezirks Saar-Hochwald.
 *
 * Nummernschema der DPSG: Diözesanverband-Bezirk-Stamm (21 = DV Trier,
 * 06 = Bezirk Saar-Hochwald). Nummern, Websites und Koordinaten laut
 * Linkliste von dpsg-trier.de (Stand 01.10.2026), Beschreibungen von der
 * bisherigen statischen Bezirksseite (Release 1.0).
 *
 * Koordinaten sind die Ortsmitte bzw. der Stadtteil, keine Heim-Adressen.
 * Sie gelten als "ungefähr", bis die Verwaltung den genauen Standort im
 * Backend per Klick setzt (position_gesetzt_von).
 *
 * Idempotent und schonend: Stämme werden nur angelegt, wenn ihre Nummer
 * noch nicht existiert; die Bezirksnummer nur gesetzt, wenn leer.
 */
class StaemmeSeeder extends Seeder
{
    public function run(): void
    {
        $bezirk = Gruppierung::query()->where('typ', 'bezirk')->where('slug', 'saar-hochwald')->first();
        if ($bezirk) {
            $bezirk->nummer ??= '21-06-00';
            $bezirk->lat ??= 49.3137;
            $bezirk->lng ??= 6.7517;
            $bezirk->save();
        }

        // [Nummer, Name, Ort, Website, Breitengrad, Längengrad, Beschreibung]
        $staemme = [
            ['21-06-01', 'Roden, Graf Folke Bernadotte', 'Saarlouis-Roden', 'https://www.dpsg-roden.de', 49.339962, 6.751531,
                'Der Stamm mit Gruppenheim in Roden ist einer der Gründungsstämme des Bezirks und bietet seit vielen Jahren Gruppenstunden für Biber, Wölflinge, Jungpfadfinder*innen, Pfadfinder*innen und Rover an.'],
            ['21-06-02', 'Perl, St. Gervasius und Protasius', 'Perl', null, 49.502012, 6.429248,
                'Der Stamm St. Gervasius und Protasius in Perl liegt im äußersten Westen des Saarlandes, nahe der Grenze zu Frankreich und Luxemburg.'],
            ['21-06-04', 'Rehlingen, St. Nikolaus', 'Rehlingen-Siersburg', 'https://www.dpsg-rehlingen.de', 49.377112, 6.684664,
                'Der Stamm St. Nikolaus in Rehlingen-Siersburg ist fest in der Gemeinde verwurzelt und freut sich über neue Mitglieder jeden Alters.'],
            ['21-06-07', 'Saarlouis-Beaumarais', 'Saarlouis-Beaumarais', 'http://www.dpsg-beaumarais.de/', 49.314646, 6.721787,
                'Der Stamm Beaumarais ist im Saarlouiser Stadtteil Beaumarais beheimatet und lebt die internationale Ausrichtung der Pfadfinderei.'],
            ['21-06-08', 'Hülzweiler, St. Laurentius', 'Schwalbach-Hülzweiler', 'http://www.dpsg-huelzweiler.de', 49.320637, 6.822628,
                'Der Stamm St. Laurentius in Hülzweiler engagiert sich in der Pfarrei und im Ortsleben und bietet Gruppenstunden für alle Altersstufen.'],
        ];

        $sort = 0;
        foreach ($staemme as [$nummer, $name, $ort, $website, $lat, $lng, $beschreibung]) {
            if (Gruppierung::query()->where('nummer', $nummer)->exists()) {
                continue;
            }

            $slug = str($name)->slug()->toString();
            if (Gruppierung::query()->where('slug', $slug)->exists()) {
                $slug = str($nummer.' '.$name)->slug()->toString();
            }

            Gruppierung::query()->create([
                'typ' => 'stamm',
                'nummer' => $nummer,
                'parent_id' => $bezirk?->id,
                'name' => $name,
                'slug' => $slug,
                'ort' => $ort,
                'website_url' => $website,
                'beschreibung' => $beschreibung,
                'lat' => $lat,
                'lng' => $lng,
                'active' => true,
                'sort' => $sort += 10,
            ]);
        }
    }
}
