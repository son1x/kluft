<?php

namespace Tests\Feature\Monitoring;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Kennzeichnung des Testsystems nach dem Livegang (docs/livegang.md).
 */
class TestsystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Page::query()->create(['slug' => 'impressum', 'title' => 'Impressum', 'body' => '<p>Impressum</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    #[TestDox('Ohne Schalter: kein Band, kein noindex')]
    public function test_aus(): void
    {
        $this->get('/impressum')->assertOk()
            ->assertDontSee('Testsystem.')
            ->assertDontSee('noindex')
            ->assertHeaderMissing('X-Robots-Tag');
    }

    #[TestDox('Offen: Band mit Link zur Website, noindex als Meta und Header')]
    public function test_offen(): void
    {
        config(['kluft.testsystem' => 'offen', 'kluft.live_url' => 'https://www.dpsg-trier.de/']);

        $this->get('/impressum')->assertOk()
            ->assertSee('Testsystem.')
            ->assertSee('href="https://www.dpsg-trier.de"', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow" />', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    #[TestDox('Geschlossen: Gäste landen bei der Anmeldung, Angemeldete sehen die Seite; Status bleibt frei')]
    public function test_geschlossen(): void
    {
        config(['kluft.testsystem' => 'geschlossen']);

        $this->get('/impressum')->assertRedirect(url('/admin/login'));
        // Systemstatus bleibt frei (Monitoring); 503 wäre ein Prüfergebnis, keine Sperre.
        $this->assertFalse($this->get('/api/status')->isRedirect());
        $this->assertFalse($this->get('/redaktion/anmelden')->isRedirect());

        $user = User::query()->create(['name' => 'R', 'email' => 'r@example.org', 'status' => 'active']);
        $this->actingAs($user)->get('/impressum')->assertOk()->assertSee('Testsystem.');
    }
}
