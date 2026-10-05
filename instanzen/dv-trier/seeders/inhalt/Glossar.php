<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Startbestand des Glossars (nur außerhalb der Tests).
 *
 * Früher Teil der Migration 2026_10_02_150000_create_glossary_terms_table.
 */
class Glossar extends Seeder
{
    public const DEFAULTS = [
        'DAK' => 'Diözesanarbeitskreis: Arbeitskreis einer Altersstufe auf Ebene des Diözesanverbands, plant Aktionen und Fortbildungen für die Stufe.',
        'FAK' => 'Facharbeitskreis: Arbeitskreis zu einem Fachthema wie Inklusion, Ökologie oder Internationale Gerechtigkeit.',
        'DL' => 'Diözesanleitung: Diözesanvorstand sowie die Referent*innen der Stufen und Fachbereiche.',
        'DV' => 'Diözesanverband: alle Stämme und Bezirke der DPSG im Bistum Trier.',
        'BDKJ' => 'Bund der Deutschen Katholischen Jugend: Dachverband der katholischen Jugendverbände.',
        'Juleica' => 'Jugendleiter*in-Card: bundesweiter Ausweis für ausgebildete ehrenamtliche Jugendleiter*innen.',
        'PSG' => 'Pfadfinderinnenschaft St. Georg',
        'VCP' => 'Verband Christlicher Pfadfinder*innen',
        'KDG' => 'Gesetz über den Kirchlichen Datenschutz – das Datenschutzrecht der katholischen Kirche.',
        'ASB' => 'Asociación de Scouts de Bolivia: der Pfadfinderverband Boliviens, Partner der Bolivienpartnerschaft.',
    ];

    public function run(): void
    {
        if (! app()->runningUnitTests()) {
            foreach (self::DEFAULTS as $term => $explanation) {
                DB::table('glossary_terms')->insert(['term' => $term, 'explanation' => $explanation, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
