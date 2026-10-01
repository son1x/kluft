<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deckt die öffentlichen Seiten-/Blog-Routen ab (Pflichtenheft 6.1
 * F-CMS-01/03, 11.1).
 */
class PublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_page_is_visible(): void
    {
        Page::query()->create([
            'title' => 'Über uns',
            'slug' => 'ueber-uns',
            'body' => '<p>Inhalt</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/ueber-uns')->assertOk()->assertSee('Über uns');
    }

    public function test_draft_page_is_not_publicly_visible(): void
    {
        Page::query()->create([
            'title' => 'Entwurf',
            'slug' => 'entwurf-seite',
            'body' => '<p>Inhalt</p>',
            'status' => 'draft',
        ]);

        $this->get('/entwurf-seite')->assertNotFound();
    }

    public function test_page_scheduled_in_the_future_is_not_yet_visible(): void
    {
        Page::query()->create([
            'title' => 'Zukünftig',
            'slug' => 'zukuenftig',
            'body' => '<p>Inhalt</p>',
            'status' => 'published',
            'published_at' => now()->addWeek(),
        ]);

        $this->get('/zukuenftig')->assertNotFound();
    }

    public function test_homepage_falls_back_to_placeholder_without_start_page(): void
    {
        $this->get('/')->assertOk()->assertSee('im Aufbau');
    }

    public function test_homepage_shows_start_page_once_published(): void
    {
        Page::query()->create([
            'title' => 'Start',
            'slug' => 'start',
            'body' => '<p>Echte Startseite</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/')->assertOk()->assertSee('Echte Startseite');
    }

    public function test_homepage_shows_latest_published_posts_and_stage_tiles(): void
    {
        Page::query()->create(['title' => 'Start', 'slug' => 'start', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        Page::query()->create(['title' => 'Biber', 'slug' => 'stufe-biber', 'stage' => 'biber', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        Post::query()->create(['title' => 'Sichtbare Meldung', 'slug' => 'sichtbar', 'teaser' => 't', 'body' => 'b', 'status' => 'published', 'published_at' => now()->subHour()]);
        Post::query()->create(['title' => 'Entwurfsmeldung', 'slug' => 'entwurf', 'teaser' => 't', 'body' => 'b', 'status' => 'draft']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Sichtbare Meldung')
            ->assertDontSee('Entwurfsmeldung')
            ->assertSee('/stufe-biber', false)
            ->assertSee('4 bis 7 Jahre');
    }

    public function test_dioezesanleitung_is_main_page_and_buero_is_subpage_of_kontakt(): void
    {
        $this->seed(\Database\Seeders\RequiredPagesSeeder::class);
        $this->seed(\Database\Seeders\DemoContentSeeder::class);

        $nav = fn (string $html) => \Illuminate\Support\Str::between($html, 'aria-label="Hauptnavigation"', '</nav>');

        $start = $this->get('/stufen')->assertOk()->getContent();
        $this->assertStringContainsString('Diözesanleitung', $nav($start));
        $this->assertStringNotContainsString('Diözesanbüro', $nav($start));

        $this->get('/dioezesanleitung')->assertOk()->assertSee('Diözesanvorstand')->assertDontSee('Geschäftsführender Bildungsreferent');
        $this->get('/dioezesanbuero')->assertOk()
            ->assertSee('Brotkrumen')
            ->assertSee('Geschäftsführender Bildungsreferent')
            ->assertDontSee('Diözesankurat');
    }

    public function test_published_post_appears_in_index_and_show(): void
    {
        Post::query()->create([
            'title' => 'Erster Beitrag',
            'slug' => 'erster-beitrag',
            'teaser' => 'Kurzer Teaser',
            'body' => '<p>Inhalt</p>',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $this->get('/aktuelles')->assertOk()->assertSee('Erster Beitrag');
        $this->get('/aktuelles/erster-beitrag')->assertOk()->assertSee('Kurzer Teaser');
    }

    public function test_draft_post_does_not_appear_in_index_or_show(): void
    {
        Post::query()->create([
            'title' => 'Unveröffentlicht',
            'slug' => 'unveroeffentlicht',
            'teaser' => 'Teaser',
            'body' => '<p>Inhalt</p>',
            'status' => 'draft',
        ]);

        $this->get('/aktuelles')->assertOk()->assertDontSee('Unveröffentlicht');
        $this->get('/aktuelles/unveroeffentlicht')->assertNotFound();
    }

    public function test_required_legal_pages_are_reachable(): void
    {
        Page::query()->create(['title' => 'Impressum', 'slug' => 'impressum', 'body' => 'x', 'status' => 'published', 'published_at' => now()->subDay()]);
        Page::query()->create(['title' => 'Datenschutz', 'slug' => 'datenschutz', 'body' => 'x', 'status' => 'published', 'published_at' => now()->subDay()]);
        Page::query()->create(['title' => 'Kontakt', 'slug' => 'kontakt', 'body' => 'x', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get('/impressum')->assertOk();
        $this->get('/datenschutz')->assertOk();
        $this->get('/kontakt')->assertOk();
    }
}
