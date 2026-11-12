<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Startseiten-Hinweis auf die Aufarbeitung sexualisierter Gewalt in der
 * DPSG (Bundesverband). Danach im Backend unter Inhalte → Startseiten-
 * Hinweise bearbeitbar; läuft die Migration erneut, bleibt er unberührt.
 */
return new class extends Migration
{
    private const LINK = 'https://dpsg.de/de/verbandsleben/themen/kinder-und-jugendschutz/aufarbeitung';

    /** In Tests nur auf ausdrücklichen Wunsch (andere Tests erwarten keine Hinweise). */
    public bool $auchInTests = false;

    public function up(): void
    {
        if ((app()->runningUnitTests() && ! $this->auchInTests) || DB::table('startseiten_hinweise')->where('link_url', self::LINK)->exists()) {
            return;
        }

        DB::table('startseiten_hinweise')->insert([
            'titel' => 'Aufarbeitung sexualisierter Gewalt in der DPSG',
            'text' => 'Die DPSG stellt sich ihrer Verantwortung und arbeitet Fälle sexualisierter Gewalt im Verband auf. Informationen zur Aufarbeitung sowie zu Ansprech- und Beratungsstellen findest du beim Bundesverband.',
            'link_url' => self::LINK,
            'link_text' => 'Zur Aufarbeitung auf dpsg.de',
            'farbe' => 'rot',
            'aktiv' => true,
            'sort' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('startseiten_hinweise')->where('link_url', self::LINK)->delete();
    }
};
