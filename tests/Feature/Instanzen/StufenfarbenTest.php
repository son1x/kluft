<?php

namespace Tests\Feature\Instanzen;

use App\Models\Page;
use App\Support\Einrichtung\StammVorlage;
use App\Support\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Abweichende Stufenfarben je Instanz (config dpsg.stage_colors), z. B.
 * gelbe Biber in Roden.
 */
class StufenfarbenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Page::query()->create(['title' => 'Start', 'slug' => 'start', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    #[TestDox('Ohne Angabe kein zusätzliches CSS, die Farben aus app.css gelten')]
    public function test_ohne_angabe(): void
    {
        config(['dpsg.stage_colors' => []]);

        $this->assertSame('', Site::stufenfarbenCss());
        $this->get('/')->assertOk()->assertDontSee('--color-stufe-', false);
    }

    #[TestDox('Gelbe Biber mit dunkler Schrift landen als CSS-Variablen im Kopf jeder Seite')]
    public function test_gelbe_biber(): void
    {
        config(['dpsg.stage_colors' => ['biber' => ['farbe' => '#ffed00', 'schrift' => 'dunkel']]]);

        $this->get('/')->assertOk()
            ->assertSee('<style>:root { --color-stufe-biber: #ffed00; --stufe-ink-biber: var(--color-text); }</style>', false);
    }

    #[TestDox('Nur Hex-Farben und bekannte Stufen, sonst wird die Angabe ignoriert')]
    public function test_ungueltige_angaben(): void
    {
        config(['dpsg.stage_colors' => [
            'biber' => ['farbe' => 'red; } body { display: none'],
            'gibtsnicht' => ['farbe' => '#000000'],
            'rover' => '#123456',
        ]]);

        $this->assertSame(':root { --color-stufe-rover: #123456; --stufe-ink-rover: #fff; }', Site::stufenfarbenCss());
    }

    #[TestDox('Stammes-Vorlage übernimmt „stufenfarben“ aus angaben.json')]
    public function test_stammvorlage(): void
    {
        $config = StammVorlage::config(['stamm' => 'Test', 'stufenfarben' => ['biber' => ['farbe' => '#ffed00', 'schrift' => 'dunkel']]]);

        $this->assertSame('#ffed00', $config['stage_colors']['biber']['farbe']);
        $this->assertSame([], StammVorlage::config(['stamm' => 'Test'])['stage_colors']);
    }
}
