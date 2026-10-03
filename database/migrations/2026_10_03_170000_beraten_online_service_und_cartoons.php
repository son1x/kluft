<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Beraten-Online nach Rückmeldung (3.10.2026):
 * - „BeratungsPower & Coaching“ ist ein Serviceangebot und steht unter
 *   „Diözesanverband“ statt unter „Fachbereiche“.
 * - Kontaktanfragen an beratung@dpsg-trier.de.
 * - Cartoons (auf Grundlage von Bildern der DPSG-Bundesebene, für das
 *   Projekt bearbeitet) auf „Stöbern & Schmökern“.
 * Ändert nur, was noch im Ausgangszustand ist. Nicht in Tests.
 */
return new class extends Migration
{
    private const SOURCE = 'database/content/beraten-online/';

    private const CARTOONS = [
        1 => 'Cartoon: Gezeichneter Pfadfinder mit Hut und verschränkten Armen, Spruchband „Versucht, die Welt ein wenig besser zu verlassen, als ihr sie vorgefunden habt.“ – PB BeratungsPower & Coaching',
        2 => 'Cartoon in drei Bildern am Strand: Ein Jugendlicher bekommt Sand ins Gesicht („Probleme sind wie Sand im Getriebe …“), ein Pfadfinder schaut aufs Meer („Lösungen gibt es wie Sand am Meer!“), gemeinsam bauen sie eine Sandburg („Eine Einladung zum Luftschlösser und Sandburgen bauen – mit BP Beratungspower & Coaching!“)',
        3 => 'Cartoon in drei Bildern im Dschungel: Eine Gruppe sitzt ratlos neben dem Schild „Stamm X“ („Verharren im gleichen und altbewährten Zustand!?“), ein Pfadfinder bricht durchs Gebüsch („Zum Finden braucht’s Bewegung im Unbekannten!“), die Gruppe wandert los („BP Beratungspower & Coaching – Pfade finden beginnt im Kopf!“)',
        4 => 'Cartoon in drei Bildern: Ein älterer Pfadfinder mit Hut sagt „Learning by doing heißt auch: Ich sorge für mich und andere“, drei junge Pfadfinder*innen zeigen den Daumen hoch: „Ihr wollt Struktur, Arbeitsaufteilung und Ergebnisse? Dann holt euch BP Beratungspower & Coaching!“',
        5 => 'Cartoon in drei Bildern: Der Pfadfinder mit Hut sagt „Look at the boy and the girl!“, Jugendliche planen ihr Jahr („Planung 2011“): „Ihr wollt wissen, wo eure Manpower steckt? Was eure Stärken sind und wie ihr das Möglichste daraus machen könnt? Dann holt euch BP Beratungspower & Coaching!“',
        6 => 'Cartoon in zwei Bildern: Der Pfadfinder paddelt im Kanu „BP-1“ („Paddle your own canoe!“), dann eine ganze Gruppe im Kanu: „Wir stehen für Werte, für klare Ziele und denken auch immer wieder über diese nach. Überraschend anders? Nutzt BP Beratungspower & Coaching!“',
    ];

    public function up(): void
    {
        if (app()->runningUnitTests() || ! DB::table('pages')->where('slug', 'beratung')->exists()) {
            return;
        }

        // Unter „Diözesanverband“ (nur wenn noch unter „Fachbereiche“).
        $fachbereiche = DB::table('pages')->where('slug', config('dpsg.sections_page'))->value('id');
        $dv = DB::table('pages')->where('slug', 'dioezesanverband')->value('id');
        if ($dv && $fachbereiche) {
            DB::table('pages')->where('slug', 'beratung')->where('parent_id', $fachbereiche)
                ->update(['parent_id' => $dv, 'nav_sort' => 60, 'updated_at' => now()]);
        }

        DB::table('contact_topics')->where('slug', 'beratung')->whereNull('email')
            ->update(['email' => 'beratung@dpsg-trier.de', 'updated_at' => now()]);

        // Cartoons auf „Stöbern & Schmökern“, vor „Zitate und Geschichten“.
        $page = DB::table('pages')->where('slug', 'stoebern-und-schmoekern')->first();
        if ($page && ! str_contains((string) $page->body, 'Cartoons') && str_contains((string) $page->body, '<h2>Zitate und Geschichten</h2>')) {
            $images = collect(self::CARTOONS)
                ->map(fn (string $alt, int $n) => [$this->media("cartoon-{$n}.jpg", $alt), $alt])
                ->filter(fn (array $m) => $m[0] !== null)
                ->map(fn (array $m) => '<p><img src="/uploads/'.DB::table('media')->where('id', $m[0])->value('path').'" alt="'.e($m[1]).'"></p>')
                ->implode("\n");
            $gallery = "<h2>Cartoons</h2>\n".$images."\n";
            DB::table('pages')->where('id', $page->id)->update([
                'body' => str_replace('<h2>Zitate und Geschichten</h2>', $gallery.'<h2>Zitate und Geschichten</h2>', (string) $page->body),
                'updated_at' => now(),
            ]);
        }
    }

    private function media(string $file, string $alt): ?int
    {
        $path = 'media/2026/beraten-online-'.$file;
        if ($id = DB::table('media')->where('path', $path)->value('id')) {
            return (int) $id;
        }
        $source = base_path(self::SOURCE.$file);
        if (! is_file($source)) {
            return null;
        }
        Storage::disk('public')->put($path, (string) file_get_contents($source));
        [$width, $height] = getimagesize($source) ?: [null, null];

        return (int) DB::table('media')->insertGetId([
            'path' => $path,
            'mime' => 'image/jpeg',
            'size' => filesize($source),
            'alt_text' => $alt,
            'credit' => 'Grundlage: Bilder der DPSG-Bundesebene, bearbeitet für BeratungsPower & Coaching (DPSG Diözesanverband Trier)',
            'rights_confirmed' => true,
            'width' => $width,
            'height' => $height,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Rein inhaltlich.
    }
};
