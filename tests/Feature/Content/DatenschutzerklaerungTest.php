<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Instanzen\DvTrier\Seeders\RequiredPagesSeeder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Datenschutzerklärung des DV: abgestimmte Fassung von dpsg-trier.de, ergänzt.
 */
#[Group('dv-trier')]
class DatenschutzerklaerungTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): void
    {
        (require base_path('instanzen/dv-trier/migrations/2026_11_07_140000_datenschutzerklaerung_abgestimmt.php'))->up();
    }

    #[TestDox('Text: KDG-Grundlagen, Rechte, neue Verarbeitungen; keine Platzhalter, nur Funktionsadressen')]
    public function test_text(): void
    {
        $text = RequiredPagesSeeder::datenschutz();

        foreach (['§ 6 Abs. 1 lit. b KDG', 'TDDDG', 'netcup', 'Brevo GmbH', 'Recht auf Unterrichtung (§ 21 KDG)', 'Überdiözesane Aufsichtsstelle', 'Anmeldung zu Veranstaltungen', 'Umfragen', 'youtube-nocookie.com', 'nach 6 Monaten', 'zählt die Website Seitenaufrufe', '„Do Not Track“', 'nach 25 Monaten gelöscht'] as $teil) {
            $this->assertStringContainsString($teil, $text);
        }
        foreach (['TODO', 'Privacy Shield', 'Telemediengesetz', 'Matomo', 'AWStats'] as $alt) {
            $this->assertStringNotContainsString($alt, $text);
        }
        preg_match_all('/[\w.+-]+@[\w.-]+/', strip_tags($text), $adressen);
        $this->assertSame(['info@dpsg-trier.de', 'info@kdsz-ffm.de'], array_values(array_unique($adressen[0])));
    }

    #[TestDox('Migration ersetzt den Entwurf aus der Einrichtung, aber keine bearbeitete Seite')]
    public function test_migration(): void
    {
        $seite = Page::query()->create(['slug' => 'datenschutz', 'title' => 'Datenschutzerklärung', 'status' => 'published', 'published_at' => now(),
            'body' => '<p><strong>Hinweis:</strong> Diese Datenschutzerklärung orientiert sich an den Angaben von dpsg-trier.de, ersetzt keine Rechtsberatung und ist vor Veröffentlichung abzustimmen.</p>']);
        $this->migration();
        $this->assertStringContainsString('dpsg trevirensis gGmbH', $seite->fresh()->body);

        $seite->update(['body' => '<p>Von Hand angepasst.</p>']);
        $this->migration();
        $this->assertSame('<p>Von Hand angepasst.</p>', $seite->fresh()->body);

        $this->get('/datenschutz')->assertOk()->assertSee('Von Hand angepasst.');
    }

    #[TestDox('Migration Seitenaufrufe: ersetzt den Absatz zur Webanalyse nur, wenn er noch wörtlich dasteht')]
    public function test_migration_seitenaufrufe(): void
    {
        $migration = require base_path('instanzen/dv-trier/migrations/2026_11_07_150100_datenschutz_seitenaufrufe.php');
        $alt = '<h2>II. Bereitstellung</h2>'.$migration::ALT.'<h2>III. Cookies</h2>';
        $seite = Page::query()->create(['slug' => 'datenschutz', 'title' => 'Datenschutzerklärung', 'status' => 'published', 'published_at' => now(), 'body' => $alt]);

        $migration->up();
        $body = $seite->fresh()->body;
        $this->assertStringContainsString('zählt die Website Seitenaufrufe', $body);
        $this->assertStringNotContainsString('Wir setzen keine Werkzeuge zur Webanalyse', $body);
        $this->assertStringStartsWith('<h2>II. Bereitstellung</h2>', $body);
        $this->assertStringEndsWith('<h2>III. Cookies</h2>', $body);
        $this->assertStringContainsString($migration::NEU, RequiredPagesSeeder::datenschutz());

        // Im Backend leicht geänderter Absatz: nichts tun.
        $angepasst = str_replace('Reichweitenmessung', 'Reichweitenmessung (Stand 2026)', $alt);
        $seite->update(['body' => $angepasst]);
        $migration->up();
        $this->assertSame($angepasst, $seite->fresh()->body);
    }

    #[TestDox('Migration Vor Ort: ergänzt Nachmelden, Unterschrift und Gerätespeicher nur einmal und nur an der unveränderten Stelle')]
    public function test_migration_vor_ort(): void
    {
        $migration = require base_path('instanzen/dv-trier/migrations/2026_11_13_150000_datenschutz_vor_ort.php');
        $alt = '<h3>Anmeldung zu Veranstaltungen</h3>'.$migration::ALT.'<h3>Umfragen</h3>';
        $seite = Page::query()->create(['slug' => 'datenschutz', 'title' => 'Datenschutzerklärung', 'status' => 'published', 'published_at' => now(), 'body' => $alt]);

        $migration->up();
        $body = $seite->fresh()->body;
        $this->assertStringContainsString('nachträglich eingetragen werden', $body);
        $this->assertStringContainsString('digital auf dem Tablet des Teams', $body);
        $this->assertStringContainsString('spätestens sieben Tage nach dem Ende der Veranstaltung', $body);
        $this->assertStringStartsWith('<h3>Anmeldung zu Veranstaltungen</h3>'.$migration::ALT, $body);
        $this->assertStringEndsWith('<h3>Umfragen</h3>', $body);

        // Zweiter Lauf ändert nichts.
        $migration->up();
        $this->assertSame($body, $seite->fresh()->body);

        // Die Datei für neue Installationen enthält dieselben Absätze.
        $this->assertStringContainsString($migration::NEU, RequiredPagesSeeder::datenschutz());

        // Von Hand geänderter Ausgangsabsatz: nichts tun.
        $angepasst = str_replace('Prüfcode', 'Prüfcode (Stand 2026)', $alt);
        $seite->update(['body' => $angepasst]);
        $migration->up();
        $this->assertSame($angepasst, $seite->fresh()->body);
    }

    #[TestDox('Seite zeigt Überschriften und Listen der neuen Fassung')]
    public function test_seite(): void
    {
        Page::query()->create(['slug' => 'datenschutz', 'title' => 'Datenschutzerklärung', 'status' => 'published', 'published_at' => now(), 'body' => RequiredPagesSeeder::datenschutz()]);

        $this->get('/datenschutz')->assertOk()
            ->assertSee('<h2>II. Bereitstellung der Website und Erstellung von Logfiles</h2>', false)
            ->assertSee('<h3>Recht auf Datenübertragbarkeit (§ 22 KDG)</h3>', false)
            ->assertSee('<li>die IP-Adresse des anfordernden Rechners</li>', false);
    }
}
