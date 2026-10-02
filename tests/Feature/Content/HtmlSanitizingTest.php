<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Redaktionelles HTML wird bei der Ausgabe bereinigt (Pflichtenheft 8.2
 * "body (HTML, bereinigt)", 10.1) – auch wenn jemand am Editor vorbei
 * Skripte speichert.
 */
class HtmlSanitizingTest extends TestCase
{
    use RefreshDatabase;

    private const EVIL = '<p>Hallo <strong>Welt</strong> <a href="https://dpsg.de">Link</a></p><script>alert(1)</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)">x</a>';

    #[TestDox('Seiten und Beiträge entfernen Skripte, behalten aber die Formatierung')]
    public function test_pages_and_posts_strip_scripts_but_keep_formatting(): void
    {
        Page::query()->create(['slug' => 'test', 'title' => 'Test', 'body' => self::EVIL, 'status' => 'published', 'published_at' => now()->subDay()]);
        Post::query()->create(['slug' => 'test', 'title' => 'Test', 'teaser' => 't', 'body' => self::EVIL, 'status' => 'published', 'published_at' => now()->subDay()]);

        foreach (['/test', '/aktuelles/test'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('<strong>Welt</strong>', false)
                ->assertSee('href="https://dpsg.de"', false)
                ->assertDontSee('alert(1)', false)
                ->assertDontSee('onerror', false)
                ->assertDontSee('javascript:', false);
        }
    }
}
