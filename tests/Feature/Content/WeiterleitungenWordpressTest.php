<?php

namespace Tests\Feature\Content;

use App\Models\Event;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * DV Trier: Die WordPress-Seiten mit eingebetteter Anmeldung leiten nach dem
 * Livegang auf die Veranstaltungen in Kluft weiter.
 */
#[Group('dv-trier')]
class WeiterleitungenWordpressTest extends TestCase
{
    use RefreshDatabase;

    private const DATEI = 'instanzen/dv-trier/migrations/2026_11_12_170000_weiterleitungen_wordpress_anmeldungen.php';

    #[TestDox('Alte WordPress-Adressen (mit und ohne Schrägstrich) führen auf die Veranstaltungen; erneut ausgeführt nichts doppelt')]
    public function test_weiterleitungen(): void
    {
        if (config('dpsg.instanz') !== 'dv-trier') {
            $this->markTestSkipped('Nur DV Trier.');
        }
        $migration = require base_path(self::DATEI);
        $migration->auchInTests = true;
        $migration->up();
        $migration->up();
        $this->assertSame(3, Redirect::query()->count());

        foreach (['adventszauber-2026', 'spiritueller-hike-2027', 'weihnachtspostkartenaktion-2026'] as $slug) {
            Event::query()->create(['title' => $slug, 'slug' => $slug, 'starts_at' => now()->addMonth(), 'status' => 'open']);
        }

        $this->get('/adventszauber-2026/')->assertRedirect('/veranstaltungen/adventszauber-2026')->assertStatus(301);
        $this->get('/spiritueller-hike-2027')->assertRedirect('/veranstaltungen/spiritueller-hike-2027');
        $this->get('/Woe-Weihnachtsaktion-2026/')->assertRedirect('/veranstaltungen/weihnachtspostkartenaktion-2026');
        $this->get('/veranstaltungen/adventszauber-2026')->assertOk();
    }
}
