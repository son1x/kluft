<?php

namespace Tests\Feature\Sections;

use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Bolivien-Sektion, Pflichtenheft 6.2 und Abnahmekriterium 3.
 */
#[Group('dv-trier')]
class BolivienSectionTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, array $attrs = []): Page
    {
        return Page::query()->create(['slug' => $slug, 'title' => ucfirst($slug), 'body' => "<p>Inhalt {$slug}</p>", 'status' => 'published', 'published_at' => now()->subDay(), ...$attrs]);
    }

    private function user(string $email, string $role, string $scopeType, ?string $scopeKey): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);

        return $user->fresh();
    }

    #[TestDox('Bolivien-Startseite zeigt nur eigene Seiten und Beiträge')]
    public function test_landing_page_shows_section_pages_and_posts_only(): void
    {
        $this->page('bolivien', ['title' => 'Bolivienpartnerschaft', 'section' => 'bolivien']);
        $this->page('bolivien-projekt', ['title' => 'Schulprojekt', 'section' => 'bolivien']);
        $this->page('andere-seite', ['title' => 'Andere Seite']);
        Post::query()->create(['title' => 'Besuch aus Sucre', 'slug' => 'besuch', 'teaser' => 't', 'body' => 'b', 'scope' => 'section', 'section' => 'bolivien', 'status' => 'published', 'published_at' => now()->subHour()]);
        Post::query()->create(['title' => 'Wölflingslager', 'slug' => 'lager', 'teaser' => 't', 'body' => 'b', 'status' => 'published', 'published_at' => now()->subHour()]);

        $this->get('/bolivien')->assertOk()
            ->assertSee('Inhalt bolivien')
            ->assertSee('Schulprojekt')
            ->assertSee('Besuch aus Sucre')
            ->assertDontSee('Wölflingslager')
            ->assertDontSee('rel="canonical"', false);

        $this->get('/bolivien/aktuelles')->assertOk()->assertSee('Besuch aus Sucre')->assertDontSee('Wölflingslager');
        $this->get('/bolivien-projekt')->assertOk()->assertSee('aria-label="Bolivienpartnerschaft"', false);
    }

    #[TestDox('bolivienpartnerschaft.de zeigt die Bolivienseite direkt an')]
    public function test_alias_domain_shows_landing_page_without_redirect_and_with_canonical(): void
    {
        config(['app.url' => 'https://www.dpsg-trier.de']);
        $this->page('start', ['title' => 'Startseite DV']);
        $this->page('bolivien', ['title' => 'Bolivienpartnerschaft', 'section' => 'bolivien']);

        $this->get('http://bolivienpartnerschaft.de/')
            ->assertOk()
            ->assertSee('Inhalt bolivien')
            ->assertDontSee('Startseite DV')
            ->assertSee('<link rel="canonical" href="https://www.dpsg-trier.de/bolivien" />', false);

        $this->get('http://www.bolivienpartnerschaft.de/')->assertOk()->assertSee('Inhalt bolivien');
        $this->get('https://www.dpsg-trier.de/')->assertOk()->assertSee('Startseite DV');
    }

    #[TestDox('Sektionsadmins verwalten nur Inhalte ihrer Sektion')]
    public function test_sektionsadmin_manages_only_section_content(): void
    {
        $sektionsadmin = $this->user('bolivien@dpsg-trier.de', 'sektionsadmin', 'section', 'bolivien');
        $stufenadmin = $this->user('biber@dpsg-trier.de', 'stufenadmin', 'stage', 'biber');
        $bolivienSeite = $this->page('bolivien', ['section' => 'bolivien']);
        $biberSeite = $this->page('stufe-biber', ['stage' => 'biber']);
        $bolivienPost = Post::query()->create(['title' => 'B', 'slug' => 'b', 'teaser' => 't', 'body' => 'b', 'scope' => 'section', 'section' => 'bolivien', 'status' => 'draft']);

        $this->assertTrue($sektionsadmin->can('update', $bolivienSeite));
        $this->assertFalse($sektionsadmin->can('update', $biberSeite));
        $this->assertTrue($sektionsadmin->can('update', $bolivienPost));
        $this->assertFalse($stufenadmin->can('update', $bolivienSeite));
        $this->assertFalse($stufenadmin->can('update', $bolivienPost));

        Filament::setCurrentPanel('admin');
        $this->actingAs($sektionsadmin);
        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$bolivienSeite])
            ->assertCanNotSeeTableRecords([$biberSeite]);
    }
}
