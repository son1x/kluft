<?php

use App\Models\Gruppenstunde;
use Database\Seeders\Einrichtung\StammStartSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roden, einmalig:
 * - Menü: Unsere Gruppen · Mitmachen · Über uns (· Termine · Aktuelles).
 *   „Mitmachen“ und „Über uns“ werden angelegt, die bisherigen Seiten
 *   darunter gehängt, nur wenn sie noch keine übergeordnete Seite haben.
 * - Gruppenstunden aus angaben.json in „Verwaltung → Gruppenstunden“, falls
 *   noch keine angelegt sind.
 * - Der Abschnitt „Gruppenstunde“ verschwindet aus dem Text der Stufenseiten;
 *   die Seiten zeigen Zeit und Ort jetzt aus der Verwaltung.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('dpsg.instanz') !== 'roden' || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }

        $mitmachen = $this->seite('mitmachen', 'Mitmachen', 20, StammStartSeeder::mitmachenText());
        $ueberUns = $this->seite('ueber-uns', 'Über uns', 30,
            '<p>Wir sind der DPSG-Stamm Graf Folke Bernadotte in Saarlouis-Roden. Hier erfährst du, wer den Stamm leitet, wo wir uns treffen und wer uns unterstützt:</p>'
            .'<ul><li><a href="/stammesvorstand">Stammesvorstand</a></li><li><a href="/pfadfinderheim">Pfadfinderheim</a></li><li><a href="/foerderverein">Förderverein</a></li></ul>');

        foreach (['stammesvorstand' => [$ueberUns, 10], 'pfadfinderheim' => [$ueberUns, 20], 'foerderverein' => [$ueberUns, 30], 'leiter-werden' => [$mitmachen, 10]] as $slug => [$eltern, $sort]) {
            DB::table('pages')->where('slug', $slug)->whereNull('parent_id')
                ->update(['parent_id' => $eltern, 'show_in_nav' => true, 'nav_sort' => $sort, 'updated_at' => now()]);
        }

        if (! Gruppenstunde::query()->exists()) {
            $zeiten = (array) config('dpsg.einrichtung.gruppenstunden', []);
            $orte = (array) config('dpsg.einrichtung.gruppenstunden_orte', []);
            foreach (array_keys((array) config('dpsg.stages')) as $i => $stage) {
                if (filled($zeiten[$stage] ?? null)) {
                    Gruppenstunde::query()->create(Gruppenstunde::ausText((string) $zeiten[$stage]) + [
                        'stage' => $stage,
                        'ort' => filled($orte[$stage] ?? null) ? (string) $orte[$stage] : null,
                        'sort' => ($i + 1) * 10,
                    ]);
                }
            }
        }

        // Abschnitt „Gruppenstunde“ am Ende der Stufenseiten entfernen, solange er
        // noch dem Startinhalt entspricht (keine weitere Überschrift dahinter).
        foreach (DB::table('pages')->whereNotNull('stage')->get(['id', 'body']) as $seite) {
            $pos = strpos((string) $seite->body, '<h2>Gruppenstunde</h2>');
            if ($pos === false) {
                continue;
            }
            $rest = substr((string) $seite->body, $pos + strlen('<h2>Gruppenstunde</h2>'));
            if (! str_contains($rest, '<h2') && strlen($rest) < 1000) {
                DB::table('pages')->where('id', $seite->id)->update(['body' => rtrim(substr((string) $seite->body, 0, $pos)), 'updated_at' => now()]);
            }
        }
    }

    private function seite(string $slug, string $titel, int $sort, string $body): int
    {
        $id = DB::table('pages')->where('slug', $slug)->value('id');

        return (int) ($id ?? DB::table('pages')->insertGetId([
            'slug' => $slug, 'title' => $titel, 'body' => $body, 'status' => 'published', 'published_at' => now(),
            'show_in_nav' => true, 'nav_sort' => $sort, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function down(): void
    {
        // Inhalte bleiben; Menü und Gruppenstunden im Backend änderbar.
    }
};
