<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Support\Galerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Bilderreihen im redaktionellen Text als Galerie (Rückmeldung Lisa:
 * Cartoons bei BeratungsPower zu groß).
 */
class GalerieTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Ab drei Bildern in Folge entsteht eine Galerie mit Link auf das große Bild')]
    public function test_reihe_wird_galerie(): void
    {
        $html = "<h2>Cartoons</h2>\n<p><img src=\"/uploads/a.jpg\" alt=\"A\"></p>\n<p><img src=\"/uploads/b.jpg\" alt=\"B\"></p>\n<p><img src=\"/uploads/c.jpg\" alt=\"C\"></p>\n<h2>Zitate</h2>";

        $out = Galerie::render($html);

        $this->assertSame(1, substr_count($out, '<ul class="galerie not-prose">'));
        $this->assertStringContainsString('<li><a href="/uploads/b.jpg" title="Bild in voller Größe öffnen"><img loading="lazy" src="/uploads/b.jpg" alt="B"></a></li>', $out);
        $this->assertStringStartsWith('<h2>Cartoons</h2>', $out);
        $this->assertStringEndsWith('<h2>Zitate</h2>', $out);
        $this->assertStringNotContainsString('<p><img', $out);
    }

    #[TestDox('Einzelne Bilder, Paare und Bilder mit Text im Absatz bleiben unverändert')]
    public function test_einzelne_bilder_bleiben(): void
    {
        $html = '<p><img src="/a.jpg" alt=""></p><p><img src="/b.jpg" alt=""></p><p>Text</p><p><img src="/c.jpg" alt=""> mit Text</p>';

        $this->assertSame($html, Galerie::render($html));
    }

    #[TestDox('Seite zeigt die Bilderreihe als Galerie')]
    public function test_seite_zeigt_galerie(): void
    {
        $bilder = implode('', array_map(fn ($n) => '<p><img src="/uploads/media/cartoon-'.$n.'.jpg" alt="Cartoon '.$n.'"></p>', [1, 2, 3, 4]));
        Page::query()->create(['title' => 'Stöbern', 'slug' => 'stoebern-galerie', 'body' => '<h2>Cartoons</h2>'.$bilder.'<h2>Zitate</h2>', 'status' => 'published', 'published_at' => now()]);

        $this->get('/stoebern-galerie')->assertOk()
            ->assertSee('class="galerie not-prose"', false)
            ->assertSee('href="/uploads/media/cartoon-4.jpg"', false)
            ->assertSeeInOrder(['Cartoons', 'Cartoon 1', 'Cartoon 4', 'Zitate']);
    }
}
