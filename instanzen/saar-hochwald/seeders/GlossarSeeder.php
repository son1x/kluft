<?php

namespace Instanzen\SaarHochwald\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Startbestand des Glossars (nur außerhalb der Tests), aus der Vorlage
 * dpsg-bezirk-saar-hochwald.
 */
class GlossarSeeder extends Seeder
{
    public const DEFAULTS = [
        'DAK' => 'Diözesanarbeitskreis: Arbeitskreis einer Altersstufe auf Ebene des Diözesanverbands, plant Aktionen und Fortbildungen für die Stufe.',
        'BAK' => 'Bezirksarbeitskreis: Arbeitskreis einer Altersstufe im Bezirk, plant Aktionen für die Gruppen aller Stämme.',
        'FAK' => 'Facharbeitskreis: Arbeitskreis zu einem Fachthema wie Inklusion, Ökologie oder Internationale Gerechtigkeit.',
        'DL' => 'Diözesanleitung: Diözesanvorstand sowie die Referent*innen der Stufen und Fachbereiche.',
        'DV' => 'Diözesanverband: alle Stämme und Bezirke der DPSG im Bistum Trier – unser Bezirk gehört zum DV Trier.',
        'BV' => 'Bezirksversammlung: höchstes Gremium des Bezirks, wählt den Bezirksvorstand.',
        'BDKJ' => 'Bund der Deutschen Katholischen Jugend: Dachverband der katholischen Jugendverbände.',
        'Juleica' => 'Jugendleiter*in-Card: bundesweiter Ausweis für ausgebildete ehrenamtliche Jugendleiter*innen.',
        'PSG' => 'Pfadfinderinnenschaft St. Georg',
        'VCP' => 'Verband Christlicher Pfadfinder*innen',
        'KDG' => 'Gesetz über den Kirchlichen Datenschutz – das Datenschutzrecht der katholischen Kirche.',
    ];

    public function run(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        foreach (self::DEFAULTS as $term => $explanation) {
            DB::table('glossary_terms')->insertOrIgnore(['term' => $term, 'explanation' => $explanation, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
