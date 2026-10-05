<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use App\Models\Download;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Download-Seite unter „Diözesanverband“ mit den Abrechnungsformularen für
 * die Diözesanebene (bisher dpsg-trier.de → Service → Formulare). Die
 * Formulare 2026 rechnen selbst (s. dateien/formulare/
 * abrechnungsformulare.py). Legt nur an, was fehlt.
 */
class DownloadsAbrechnungsformulare extends Seeder
{
    public const SEITE = [
        'slug' => 'downloads', 'title' => 'Downloads', 'nav_sort' => 60,
        'seo_description' => 'Formulare, Abrechnungen, Methoden und Arbeitshilfen zum Herunterladen',
    ];

    public const BODY = <<<'HTML'
        <p>Hier findest du Formulare, Methoden und Arbeitshilfen des Diözesanverbands zum Herunterladen. Die Abrechnungsformulare sind für alle, die auf Diözesanebene ehrenamtlich mitarbeiten: ausfüllen, ausdrucken, unterschreiben und mit den Belegen ans Diözesanbüro schicken.</p>
        HTML;

    private const QUELLE = 'instanzen/dv-trier/dateien/formulare/';

    public const DATEIEN = [
        ['datei' => 'fahrtkostenabrechnung-2026.pdf', 'name' => 'Fahrtkostenabrechnung-2026.pdf', 'sort' => 10,
            'title' => 'Fahrtkostenabrechnung (Kilometergeld)',
            'description' => 'Am PC ausfüllbar: rechnet Kilometer und Betrag (0,30 € pro km) selbst und prüft die IBAN.'],
        ['datei' => 'reisekostenabrechnung-2026.pdf', 'name' => 'Reisekostenabrechnung-2026.pdf', 'sort' => 20,
            'title' => 'Reisekostenabrechnung',
            'description' => 'Für Bahn, Bus und Bahncard. Am PC ausfüllbar: zählt die Belege, rechnet den Betrag und prüft die IBAN.'],
        ['datei' => 'sachkostenabrechnung-2026.pdf', 'name' => 'Sachkostenabrechnung-2026.pdf', 'sort' => 30,
            'title' => 'Sachkostenabrechnung',
            'description' => 'Für Telefon, Porto, Büromaterial, Geschenke und Bewirtung. Am PC ausfüllbar: zählt die Belege, rechnet den Betrag und prüft die IBAN.'],
    ];

    /** In Tests nur, wenn ausdrücklich gewünscht (schreibt Dateien). */
    public bool $auchInTests = false;

    public function run(): void
    {
        if (app()->runningUnitTests() && ! $this->auchInTests) {
            return;
        }

        $dv = DB::table('pages')->where('slug', 'dioezesanverband')->value('id');
        if ($dv && ! DB::table('pages')->where('slug', self::SEITE['slug'])->exists()) {
            DB::table('pages')->insert([
                ...self::SEITE,
                'body' => self::BODY,
                'parent_id' => $dv,
                'show_in_nav' => true,
                'status' => 'published',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::DATEIEN as $datei) {
            $pfad = 'downloads/dv-'.$datei['datei'];
            $quelle = base_path(self::QUELLE.$datei['datei']);
            if (Download::query()->where('file_path', $pfad)->exists() || ! is_file($quelle)) {
                continue;
            }
            Storage::disk(Download::DISK)->put($pfad, (string) file_get_contents($quelle));
            Download::query()->create([
                'title' => $datei['title'],
                'description' => $datei['description'],
                'category' => 'abrechnung',
                'file_path' => $pfad,
                'file_name' => $datei['name'],
                'sort' => $datei['sort'],
                'rights_confirmed' => true,
                'published' => true,
            ]);
        }
    }
}
