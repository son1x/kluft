<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Begriffserklärungen (Glossar): Abkürzungen wie DAK oder FAK werden im
 * Text beim Darüberfahren erklärt. Startbestand nur außerhalb der Tests.
 */
return new class extends Migration
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

    public function up(): void
    {
        Schema::create('glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 60)->unique();
            $table->string('explanation', 300);
            $table->timestamps();
        });

        if (! app()->runningUnitTests()) {
            foreach (self::DEFAULTS as $term => $explanation) {
                DB::table('glossary_terms')->insert(['term' => $term, 'explanation' => $explanation, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_terms');
    }
};
