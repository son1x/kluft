<?php

use App\Models\Gruppierung;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stämme bieten standardmäßig Wölflinge bis Rover an. Biber gibt es nur
 * dort, wo eine Bibergruppe ausdrücklich gemeldet ist. Stämme, deren
 * Stufen schon im Backend gepflegt wurden, bleiben unverändert.
 */
return new class extends Migration
{
    private const STANDARD = ['woelflinge', 'jupfi', 'pfadi', 'rover'];

    // Stämme mit Bibergruppe (DPSG-Nummer): Roden und Hülzweiler laut
    // Diözesanleitung, Friedrichsthal und Hillesheim laut eigener Website.
    private const MIT_BIBERN = [
        '21-05-06', // Friedrichsthal, Wikinger
        '21-06-01', // Roden, Graf Folke Bernadotte
        '21-06-08', // Hülzweiler, St. Laurentius
        '21-10-05', // Hillesheim, Balu
    ];

    public function up(): void
    {
        $gepflegt = DB::table('gruppierung_stufen')->distinct()->pluck('gruppierung_id');

        $staemme = DB::table('gruppierungen')
            ->where('typ', 'stamm')
            ->whereNotIn('id', $gepflegt)
            ->get(['id', 'nummer']);

        foreach ($staemme as $stamm) {
            $stufen = in_array($stamm->nummer, self::MIT_BIBERN, true)
                ? ['biber', ...self::STANDARD]
                : self::STANDARD;

            DB::table('gruppierung_stufen')->insert(array_map(
                fn (string $stufe) => ['gruppierung_id' => $stamm->id, 'stufe' => $stufe],
                $stufen,
            ));
        }

        if (class_exists(Gruppierung::class)) {
            Gruppierung::flushCache();
        }
    }

    public function down(): void
    {
        //
    }
};
