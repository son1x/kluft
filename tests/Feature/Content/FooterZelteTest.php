<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Fußzeile: Ohne Newsletter (Stämme, Bezirke) stehen unten rechts Zelte auf
 * der Wiese, mit Newsletter (DV) der Hinweis auf den Newsletter.
 */
class FooterZelteTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Zelte im Footer genau dann, wenn es kein Newsletter-Widget gibt')]
    public function test_zelte_ohne_newsletter(): void
    {
        Page::query()->firstOrCreate(['slug' => 'impressum'], ['title' => 'Impressum', 'body' => '<p>Impressum</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $antwort = $this->get('/impressum')->assertOk();

        if (Features::newsletter()) {
            $antwort->assertDontSee('images/footer/zelte', false)->assertSee(route('newsletter.index'), false);
        } else {
            $antwort->assertSee('images/footer/zelte.webp', false);
        }
        $this->assertFileExists(public_path('images/footer/zelte.png'));
        $this->assertFileExists(public_path('images/footer/zelte.webp'));
    }
}
