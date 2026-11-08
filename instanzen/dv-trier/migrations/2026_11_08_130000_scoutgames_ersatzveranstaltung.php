<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Scoutgames 2020: Hintergrund ergänzen, dass die digitalen Scoutgames der
 * Ersatz für das wegen Corona ausgefallene Diözesanlager waren (Quelle:
 * alte Seite scoutgames2020.info, heute iamscout.de/scoutgames2020).
 * Ändert nur den unveränderten Text aus der Archiv-Migration; im Backend
 * Bearbeitetes bleibt stehen.
 */
return new class extends Migration
{
    private const ALT_TEXT = '<p>Pfingsten 2020 fand im Netz statt: Am Pfingstsamstag, 30. Mai 2020, lud der Diözesanverband zu den <strong>digitalen Scoutgames</strong> ein – mit Impulsen, Spielen und einer Lagerfeuerrunde per Videokonferenz.</p>';

    private const NEU_TEXT = '<p>Eigentlich sollte 2020 das Diözesanlager <strong>Scoutgames</strong> stattfinden. Wegen der Corona-Pandemie fiel es aus. Damit Pfadfinder*innen trotzdem gemeinsam spielen und sich begegnen konnten, entschied sich die Diözesanleitung spontan für eine digitale Ersatzveranstaltung: die <strong>Scoutgames 2020 – digital</strong> am Pfingstsamstag, 30. Mai 2020, mit Impulsen, Spielen und einer Lagerfeuerrunde per Videokonferenz. Eingeladen waren Jung und Alt.</p>';

    private const ALT_BESCHREIBUNG = 'Digitale Scoutgames des DPSG-Diözesanverbands Trier am Pfingstsamstag, 30. Mai 2020: Impulse, Spiele und Lagerfeuerrunde im Netz.';

    private const NEU_BESCHREIBUNG = 'Digitale Scoutgames am Pfingstsamstag, 30. Mai 2020: Ersatz für das wegen Corona ausgefallene Diözesanlager des DPSG-Diözesanverbands Trier, mit Impulsen, Spielen und Lagerfeuerrunde im Netz.';

    public function up(): void
    {
        $seite = DB::table('pages')->where('slug', 'scoutgames')->first();
        if (! $seite) {
            return;
        }

        $werte = [];
        if (str_contains((string) $seite->body, self::ALT_TEXT)) {
            $werte['body'] = str_replace(self::ALT_TEXT, self::NEU_TEXT, (string) $seite->body);
        }
        if ($seite->seo_description === self::ALT_BESCHREIBUNG) {
            $werte['seo_description'] = self::NEU_BESCHREIBUNG;
        }
        if ($werte !== []) {
            DB::table('pages')->where('id', $seite->id)->update($werte + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Inhalt bleibt.
    }
};
