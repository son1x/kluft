<?php

namespace Tests\Feature\Content;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Seitennavigation der Website: deutsch und ohne unzulässige ARIA-Attribute.
 */
class SeitennavigationTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Aktuelles mit mehreren Seiten: „Seite 1 von 2“, Zurück/Weiter, aktuelle Seite markiert; kein Englisch, kein aria-label an span')]
    public function test_seitennavigation(): void
    {
        $user = User::query()->create(['name' => 'A', 'email' => 'a@example.org', 'status' => 'active']);
        foreach (range(1, 12) as $i) {
            Post::query()->create(['title' => "Beitrag {$i}", 'slug' => "beitrag-{$i}", 'status' => 'published', 'published_at' => now()->subDays($i),
                'teaser' => 'Kurz', 'body' => '<p>x</p>', 'author_id' => $user->id, 'scope' => 'general']);
        }

        $html = $this->get('/aktuelles')->assertOk()
            ->assertSee('Seite 1 von 2')->assertSee('Weiter ›')->assertSee('aria-current="page"', false)
            ->assertDontSee('pagination.')->assertDontSee('Showing')->getContent();
        $this->assertDoesNotMatchRegularExpression('/<span[^>]*aria-label=/', (string) $html);

        $this->get('/aktuelles?page=2')->assertOk()->assertSee('Seite 2 von 2')->assertSee('rel="prev"', false);
    }
}
