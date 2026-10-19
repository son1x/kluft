<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Models\Person;
use App\Support\Leitergewinnung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Leitergewinnung: auf Stammes-Websites eine eigene Seite „Leiter*in werden“
 * statt eines Aufrufs auf jeder Stufenseite.
 */
class LeitergewinnungTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, string $title, ?string $stage = null, array $attrs = []): Page
    {
        return Page::query()->create([
            'title' => $title, 'slug' => $slug, 'stage' => $stage, 'body' => '<p>Text</p>',
            'status' => 'published', 'published_at' => now()->subDay(), ...$attrs,
        ]);
    }

    private function stufenseite(): void
    {
        $this->page('stufen', 'Unsere Gruppen');
        $this->page('stufe-woelflinge', 'Wölflinge', 'woelflinge');
        Person::query()->create(['gremium' => 'dak', 'name' => 'Lea Leitend', 'stage' => 'woelflinge', 'consent' => true]);
    }

    #[TestDox('Stamm: kein „Lust mitzuarbeiten?“ auf der Stufenseite, dafür „Leiter*in werden“ auf der Startseite')]
    public function test_stamm_ohne_aufruf_auf_stufenseiten(): void
    {
        config(['dpsg.site.level' => 'Stamm']);
        $this->stufenseite();

        $this->get('/stufe-woelflinge')->assertOk()
            ->assertSee('Lea Leitend')
            ->assertDontSee('mitzuarbeiten')
            ->assertDontSee('Mitmachen im');

        $this->assertNull(Leitergewinnung::url());
        $this->page(Leitergewinnung::SLUG, Leitergewinnung::TITEL, null, ['body' => Leitergewinnung::text(), 'show_in_nav' => true]);
        $this->assertSame(url('/'.Leitergewinnung::SLUG), Leitergewinnung::url());
        $this->get('/'.Leitergewinnung::SLUG)->assertOk()->assertSee('erweitertes Führungszeugnis');
    }

    #[TestDox('Diözese/Bezirk: Aufruf zur Mitarbeit im Arbeitskreis bleibt, keine Leiter-Seite')]
    public function test_dv_behaelt_aufruf(): void
    {
        config(['dpsg.site.level' => 'Diözesanverband']);
        $this->stufenseite();
        $this->page(Leitergewinnung::SLUG, Leitergewinnung::TITEL);

        $this->get('/stufe-woelflinge')->assertOk()->assertSee('mitzuarbeiten');
        $this->assertNull(Leitergewinnung::url());
    }

    #[TestDox('Migration legt die Seite bei bestehenden Stammes-Websites einmal an, sonst nicht')]
    public function test_migration(): void
    {
        $migration = require database_path('migrations/2026_10_19_100000_leiter_werden_seite_fuer_staemme.php');
        Page::query()->where('slug', Leitergewinnung::SLUG)->delete();

        config(['dpsg.site.level' => 'Diözesanverband']);
        $this->page('start', 'Start');
        $migration->up();
        $this->assertFalse(Page::query()->where('slug', Leitergewinnung::SLUG)->exists());

        config(['dpsg.site.level' => 'Stamm']);
        $migration->up();
        $seite = Page::query()->where('slug', Leitergewinnung::SLUG)->firstOrFail();
        $this->assertTrue($seite->show_in_nav);
        $this->assertSame('published', $seite->status);

        $seite->update(['body' => '<p>Eigener Text</p>']);
        $migration->up();
        $this->assertSame(1, Page::query()->where('slug', Leitergewinnung::SLUG)->count());
        $this->assertSame('<p>Eigener Text</p>', $seite->fresh()->body);
    }
}
