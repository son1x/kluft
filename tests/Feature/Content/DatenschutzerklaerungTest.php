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

        foreach (['§ 6 Abs. 1 lit. b KDG', 'TDDDG', 'netcup', 'Brevo GmbH', 'Recht auf Unterrichtung (§ 21 KDG)', 'Überdiözesane Aufsichtsstelle', 'Anmeldung zu Veranstaltungen', 'Umfragen', 'youtube-nocookie.com', 'nach 6 Monaten'] as $teil) {
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
