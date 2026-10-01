<?php

namespace Database\Seeders;

use App\Models\Gruppierung;
use Illuminate\Database\Seeder;

/**
 * Bezirke und Stämme laut Linkliste von dpsg-trier.de
 * (https://www.dpsg-trier.de/downloads/links/, Stand 01.10.2026).
 *
 * Nummernschema der DPSG: Diözesanverband-Bezirk-Stamm, z. B. 21-01-08
 * (21 = DV Trier, 01 = Bezirk Koblenz, 08 = Stamm). Bezirke tragen die
 * Stammesnummer 00.
 *
 * Koordinaten sind die Ortsmitte (bzw. der Stadtteil) des Stammes, einmalig
 * aus OpenStreetMap ermittelt – keine Geocodierung in der Anwendung
 * (F-MAP-05). Sie gelten als "ungefähr", bis die Verwaltung den genauen
 * Standort im Backend per Klick setzt (position_gesetzt_von).
 *
 * Websites: nur übernommen, wenn die Domain noch antwortet; per HTTPS
 * erreichbare Seiten mit https-Adresse.
 *
 * Idempotent und schonend: Stämme werden nur angelegt, wenn ihre Nummer
 * noch nicht existiert; Bezirks-Websites nur gesetzt, wenn leer.
 */
class StaemmeSeeder extends Seeder
{
    public function run(): void
    {
        $bezirke = [
            '01' => ['slug' => 'koblenz', 'website_url' => 'https://dpsg-koblenz.de'],
            '02' => ['slug' => 'mittelmosel', 'website_url' => null],
            '03' => ['slug' => 'rhein-hunsrueck-nahe', 'website_url' => null],
            '04' => ['slug' => 'rhein-wied', 'website_url' => 'http://www.dpsg-rhein-wied.de/'],
            '05' => ['slug' => 'saarbruecken', 'website_url' => 'https://www.dpsg-saarbruecken.de'],
            '06' => ['slug' => 'saar-hochwald', 'website_url' => null],
            '07' => ['slug' => 'schaumberg-blies', 'website_url' => null],
            '08' => ['slug' => 'sieg', 'website_url' => 'http://www.dpsg-bezirk-sieg.de'],
            '09' => ['slug' => 'trier', 'website_url' => 'http://www.dpsg-bezirk-trier.de'],
            '10' => ['slug' => 'eifel-ahr', 'website_url' => 'http://www.dpsg-eifel-ahr.de'],
        ];

        $bezirkIds = [];
        foreach ($bezirke as $bb => $data) {
            $bezirk = Gruppierung::query()->where('typ', 'bezirk')->where('slug', $data['slug'])->first();
            if (! $bezirk) {
                continue;
            }
            $bezirk->nummer ??= "21-{$bb}-00";
            $bezirk->website_url ??= $data['website_url'];
            $bezirk->save();
            $bezirkIds[$bb] = $bezirk->id;
        }

        // [Nummer, Name, Ort, Website, Breitengrad, Längengrad]
        $staemme = [
            ['21-01-01', 'Koblenz-Metternich, St. Konrad', 'Koblenz-Metternich', null, 50.365976, 7.556738],
            ['21-01-04', 'Kaltenengers', 'Kaltenengers', null, 50.4157, 7.544239],
            ['21-01-06', 'Urmitz', 'Urmitz', null, 50.414715, 7.519051],
            ['21-01-07', 'Koblenz-Niederberg, Castell', 'Koblenz-Niederberg', null, 50.370656, 7.627554],
            ['21-01-08', 'Koblenz, St. Josef', 'Koblenz', 'http://www.dpsg-sankt-josef.de', 50.346174, 7.590969],
            ['21-01-09', 'Koblenz-Goldgrube, St. Franziskus', 'Koblenz-Goldgrube', 'https://www.dpsg-frisko.de', 50.35445, 7.579554],
            ['21-01-10', 'Koblenz-Güls, Seepfadfinder', 'Koblenz-Güls', 'https://www.seepfadfinder-koblenz.de', 50.344032, 7.548153],
            ['21-01-11', 'Rhens, St. Theresia', 'Rhens', 'https://www.dpsg-rhens.de', 50.281527, 7.616298],
            ['21-01-14', 'Bassenheim, St. Martin', 'Bassenheim', 'http://www.pfadfinder-bassenheim.de', 50.35877, 7.456835],
            ['21-01-16', 'Koblenz-Asterstein', 'Koblenz-Asterstein', 'https://dpsg-asterstein.de', 50.350037, 7.61812],
            ['21-01-17', 'Maifeld-Scouts', 'Maifeld', null, 50.298748, 7.312955],
            ['21-02-01', 'Bernkastel-Kues, Moselfranken', 'Bernkastel-Kues', null, 49.91574, 7.070818],
            ['21-02-02', 'Bernkastel-Kues, Westgoten', 'Bernkastel-Kues', 'https://westgoten.de', 49.910113, 7.061414],
            ['21-02-05', 'Rachtig, Deutschherren', 'Zeltingen-Rachtig', 'https://pfadfinder-rachtig.de', 49.96176, 7.001545],
            ['21-02-07', 'Kaisersesch', 'Kaisersesch', 'http://www.dpsg-kaisersesch.de', 50.230799, 7.140485],
            ['21-02-12', 'Wittlich, St. Rochus', 'Wittlich', null, 49.985035, 6.88844],
            ['21-03-01', 'Kirn-Sulzbach, St. Michael', 'Kirn-Sulzbach', 'http://www.dpsg.kirn-sulzbach.de', 49.763775, 7.434109],
            ['21-03-03', 'Bad Kreuznach, Franziskus', 'Bad Kreuznach', 'https://dpsg-kh.de', 49.815347, 7.912522],
            ['21-03-04', 'Rheinböllen, Sonnwald', 'Rheinböllen', 'https://dpsg-rheinboellen.de', 50.008697, 7.672456],
            ['21-03-05', 'Waldalgesheim, Alagastesheim', 'Waldalgesheim', 'https://www.pfadfinder-waldalgesheim.de', 49.951475, 7.834703],
            ['21-03-06', 'Sohren', 'Sohren', null, 49.931108, 7.308192],
            ['21-03-07', 'Bingerbrück, St. Rupertus', 'Bingen-Bingerbrück', 'http://www.dpsg-bingerbrueck.de/wordpress', 49.96881, 7.883485],
            ['21-04-01', 'Neuwied-Oberbieber', 'Neuwied-Oberbieber', 'https://www.pfadfinder-oberbieber.de', 50.473709, 7.501576],
            ['21-04-02', 'Kurtscheid-Rengsdorf', 'Kurtscheid', 'http://www.dpsg-kurtscheid.de/Homepage_neu/', 50.535506, 7.464768],
            ['21-04-03', 'Neuwied-Feldkirchen, St. Michael', 'Neuwied-Feldkirchen', 'https://dpsgstmichael.wordpress.com', 50.451588, 7.422418],
            ['21-04-04', 'Neuwied-City', 'Neuwied', 'https://www.dpsg-neuwied-city.de', 50.430239, 7.466302],
            ['21-04-05', 'Marienhausen', 'Marienhausen', null, 50.562944, 7.691333],
            ['21-04-06', 'Rheinbrohl', 'Rheinbrohl', null, 50.497713, 7.330199],
            ['21-04-08', 'Bad Hönningen', 'Bad Hönningen', null, 50.515738, 7.306592],
            ['21-04-10', 'Peterslahr', 'Peterslahr', null, 50.614055, 7.482337],
            ['21-04-12', 'Neuwied-Engers, St. Martin', 'Neuwied-Engers', 'http://www.dpsg-engers.de', 50.428657, 7.545483],
            ['21-04-13', 'Neuwied-Heimbach-Weis', 'Neuwied-Heimbach-Weis', 'https://dpsg-heimbach-weis.de', 50.450135, 7.543753],
            ['21-04-14', 'Neuwied-Gladbach', 'Neuwied-Gladbach', 'https://dpsg-gladbach.de', 50.459242, 7.520577],
            ['21-04-17', 'Leutesdorf', 'Leutesdorf', null, 50.450384, 7.386479],
            ['21-05-02', 'Dudweiler, St. Bonifatius', 'Saarbrücken-Dudweiler', 'http://www.dpsg-dudweiler.de', 49.277321, 7.034361],
            ['21-05-03', 'Gersweiler-Ottenhausen', 'Saarbrücken-Gersweiler', null, 49.235823, 6.929621],
            ['21-05-05', 'Köllerbach, Herz-Jesu', 'Püttlingen-Köllerbach', 'http://www.dpsg-koellerbach.de', 49.306011, 6.900886],
            ['21-05-06', 'Friedrichsthal, Wikinger', 'Friedrichsthal', 'https://www2.dpsg-friedrichsthal.de', 49.321876, 7.09642],
            ['21-05-07', 'Klarenthal, St. Bartholomäus', 'Saarbrücken-Klarenthal', 'https://dpsg-klarenthal.de', 49.230721, 6.897357],
            ['21-05-08', 'St. Ingbert-Rentrisch', 'St. Ingbert-Rentrisch', null, 49.265922, 7.084902],
            ['21-05-11', 'Ludweiler', 'Völklingen-Ludweiler', null, 49.209736, 6.788331],
            ['21-05-12', 'Saarbrücken-Brebach', 'Saarbrücken-Brebach', null, 49.199832, 7.063638],
            ['21-06-01', 'Roden, Graf Folke Bernadotte', 'Saarlouis-Roden', 'https://www.dpsg-roden.de', 49.339962, 6.751531],
            ['21-06-02', 'Perl', 'Perl', null, 49.502012, 6.429248],
            ['21-06-04', 'Rehlingen, St. Nikolaus', 'Rehlingen-Siersburg', 'https://www.dpsg-rehlingen.de', 49.377112, 6.684664],
            ['21-06-07', 'Saarlouis-Beaumarais', 'Saarlouis-Beaumarais', 'http://www.dpsg-beaumarais.de/', 49.314646, 6.721787],
            ['21-06-08', 'Hülzweiler, St. Laurentius', 'Schwalbach-Hülzweiler', 'http://www.dpsg-huelzweiler.de', 49.320637, 6.822628],
            ['21-07-02', 'Illingen, Adler', 'Illingen', null, 49.377724, 7.052515],
            ['21-07-04', 'Hasborn-Dautweiler', 'Tholey-Hasborn', 'http://www.dpsg-hasborn.de', 49.493611, 6.980433],
            ['21-07-12', 'Neunkirchen, St. Pius', 'Neunkirchen (Saar)', null, 49.34987, 7.184156],
            ['21-08-02', 'Kirchen, St. Michael', 'Kirchen (Sieg)', 'http://www.dpsg-bezirk-sieg.de/kirc/kirc.htm', 50.808126, 7.87987],
            ['21-08-03', 'Niederfischbach', 'Niederfischbach', 'https://www.dpsg-bezirk-sieg.de/nfb/nfb.htm', 50.849728, 7.888003],
            ['21-08-04', 'Betzdorf, St. Ignatius', 'Betzdorf', 'https://www.dpsg-bezirk-sieg.de/betz/betz.htm', 50.787996, 7.87238],
            ['21-08-05', 'Niederschelderhütte / Birken', 'Niederschelderhütte', 'https://www.dpsg-bezirk-sieg.de/nsch', 50.840345, 7.961498],
            ['21-09-02', 'Trier-Euren, Helena/Tempelherren', 'Trier-Euren', null, 49.735502, 6.600511],
            ['21-09-03', 'Trier-Pfalzel, Pankratius', 'Trier-Pfalzel', null, 49.780247, 6.692911],
            ['21-09-06', 'Saarburg, St. Franziskus', 'Saarburg-Beurig', 'http://pfadfindersaarburg-beurig.jimdo.com/', 49.6055, 6.563104],
            ['21-09-07', 'Schweich', 'Schweich', null, 49.82243, 6.751542],
            ['21-09-10', 'Trier-Heiligkreuz, Santa Cruz', 'Trier-Heiligkreuz', 'http://dpsg-santacruz.jimdo.com/', 49.7369, 6.647764],
            ['21-09-13', 'Föhren, Don Bosco', 'Föhren', null, 49.859309, 6.767826],
            ['21-10-01', 'Adenau', 'Adenau', 'https://dpsg-adenau.de', 50.382328, 6.9304],
            ['21-10-03', 'Grafschaft-Ringen, G. Galilei', 'Grafschaft-Ringen', null, 50.571677, 7.095545],
            ['21-10-04', 'Andernach, St. Michael', 'Andernach', 'https://www.dpsg-andernach.de', 50.439192, 7.402315],
            ['21-10-05', 'Hillesheim', 'Hillesheim', 'https://stamm-balu.de', 50.291671, 6.668001],
            ['21-10-06', 'Weißenthurm und Kettig, Feuer und Flamme', 'Weißenthurm', null, 50.414519, 7.455754],
            ['21-10-07', 'Kruft, Dionysius', 'Kruft', null, 50.387391, 7.338819],
            ['21-10-10', 'Saffig', 'Saffig', null, 50.381272, 7.416545],
            ['21-10-11', 'Andernach-Eich', 'Andernach-Eich', null, 50.427373, 7.353613],
        ];

        foreach ($staemme as [$nummer, $name, $ort, $website, $lat, $lng]) {
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
                'parent_id' => $bezirkIds[substr($nummer, 3, 2)] ?? null,
                'name' => $name,
                'slug' => $slug,
                'ort' => $ort,
                'website_url' => $website,
                'lat' => $lat,
                'lng' => $lng,
                'active' => true,
            ]);
        }
    }
}
