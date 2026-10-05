<?php

namespace Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Instanzen\DvTrier\Seeders\DemoContentSeeder;
use Instanzen\DvTrier\Seeders\RequiredPagesSeeder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Performance (Pflichtenheft 10.3): Logo und Stufen-Maskottchen als WebP mit
 * PNG als Rückfall; Seiten haben immer eine Beschreibung.
 */
#[Group('dv-trier')]
class StaticImagesTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Logo und Maskottchen kommen als WebP (PNG als Rückfall), das Rover-SVG bleibt SVG')]
    public function test_webp_sources(): void
    {
        $this->seed([RequiredPagesSeeder::class, DemoContentSeeder::class]);

        $html = $this->get('/stufe-woelflinge')->assertOk()->getContent();
        $this->assertStringContainsString('<source type="image/webp" srcset="'.asset('images/logo-dv-trier.webp').'"', $html);
        $this->assertStringContainsString('<source type="image/webp" srcset="'.asset('images/stufen/woelflinge.webp').'"', $html);
        $this->assertStringContainsString('src="'.asset('images/stufen/woelflinge.png').'"', $html);
        $this->assertStringNotContainsString('rover.webp', $html);
        $this->assertStringContainsString('x-bind:class="(scrolled || navOpen)', $html);
    }

    #[TestDox('Jede Seite hat eine Meta-Beschreibung')]
    public function test_meta_description_fallback(): void
    {
        $this->get('/meine-anmeldungen')->assertOk()->assertSee('<meta name="description" content="Deutsche Pfadfinderschaft Sankt Georg im Bistum Trier', false);
    }
}
