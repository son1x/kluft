<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Support\Embeds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Zwei-Klick-Einbettungen für Videos und Karten (Pflichtenheft 6.8).
 */
class EmbedsTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Links auf YouTube, Vimeo, OpenStreetMap und Google Maps werden erkannt; Einbett-Adresse aus geprüften Teilen')]
    public function test_match(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', Embeds::match('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10')['embed']);
        $this->assertSame('youtube', Embeds::match('https://youtu.be/dQw4w9WgXcQ')['provider']);
        $this->assertSame('youtube', Embeds::match('https://www.youtube.com/shorts/dQw4w9WgXcQ')['provider']);
        $this->assertSame('https://player.vimeo.com/video/76979871?dnt=1', Embeds::match('https://vimeo.com/76979871')['embed']);
        $osm = Embeds::match('https://www.openstreetmap.org/#map=16/49.75565/6.63935');
        $this->assertStringStartsWith('https://www.openstreetmap.org/export/embed.html?bbox=', $osm['embed']);
        $this->assertStringContainsString('marker=49.75565,6.63935', $osm['embed']);
        $this->assertSame('https://maps.google.com/maps?q=49.7556%2C6.6393&output=embed', Embeds::match('https://www.google.com/maps/place/Jugendhaus/@49.7556,6.6393,17z')['embed']);

        $this->assertNull(Embeds::match('https://www.youtube.com/watch?v=kaputt"onload=x'));
        $this->assertNull(Embeds::match('javascript:alert(1)'));
        $this->assertNull(Embeds::match('https://example.org/video'));
    }

    #[TestDox('Nur ein Link allein im Absatz wird zum Platzhalter – ohne Anfrage an den Anbieter, Link im Fließtext bleibt Link')]
    public function test_render_on_page(): void
    {
        Page::query()->create([
            'title' => 'Film', 'slug' => 'film', 'status' => 'published', 'published_at' => now()->subDay(),
            'body' => '<p>Unser Film:</p><p><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">Rückblick Pfingstlager</a></p>'
                .'<p>Mehr auf <a href="https://youtu.be/aaaaaaaaaaa">YouTube</a> im Text.</p>',
        ]);

        $response = $this->get('/film')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('x-data="zweiKlick"', $html);
        $this->assertStringContainsString('data-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0"', $html);
        $this->assertStringContainsString('Rückblick Pfingstlager', $html);
        $this->assertStringContainsString('Video laden', $html);
        // Alpine-Attribute müssen die HTML-Umwandlung überstehen („@click“ täte das nicht).
        $this->assertStringContainsString('x-on:click="load()"', $html);
        $this->assertStringContainsString('x-bind:src="src"', $html);
        $this->assertStringNotContainsString('<iframe src=', $html);
        $this->assertSame(1, substr_count($html, 'x-data="zweiKlick"'));
        $this->assertStringContainsString('<a href="https://youtu.be/aaaaaaaaaaa">YouTube</a> im Text', $html);

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression('#frame-src [^;]*https://www\.youtube-nocookie\.com#', $csp);
    }
}
