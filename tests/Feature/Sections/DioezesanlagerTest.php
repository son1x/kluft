<?php

namespace Tests\Feature\Sections;

use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Support\Lager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Rubrik „Diözesanlager“ mit dem Archiv „Scoutergy“ (FroLeiLa 2023).
 */
#[Group('dv-trier')]
class DioezesanlagerTest extends TestCase
{
    use RefreshDatabase;

    private ?string $public = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Kopien der Bilder nicht ins echte public/uploads schreiben.
        $this->public = sys_get_temp_dir().'/kluft-lager-'.uniqid();
        File::ensureDirectoryExists($this->public.'/uploads');
        config(['filesystems.disks.public.root' => $this->public.'/uploads']);
    }

    protected function tearDown(): void
    {
        if ($this->public !== null) {
            File::deleteDirectory($this->public);
        }
        parent::tearDown();
    }

    private function page(string $slug, array $attrs = []): Page
    {
        return Page::query()->create(['slug' => $slug, 'title' => ucfirst($slug), 'body' => "<p>Inhalt {$slug}</p>", 'status' => 'published', 'published_at' => now()->subDay(), ...$attrs]);
    }

    private function migrieren(): void
    {
        $this->page('start');
        $this->page('dioezesanverband', ['title' => 'Diözesanverband', 'show_in_nav' => true]);
        $this->lauf();
    }

    private function lauf(): void
    {
        // public_path nur für die Migration umbiegen (Vite braucht das echte).
        $echt = public_path();
        $this->app->usePublicPath($this->public);
        try {
            (require base_path('instanzen/dv-trier/migrations/2026_10_31_100000_dioezesanlager_scoutergy.php'))->up();
        } finally {
            $this->app->usePublicPath($echt);
        }
    }

    #[TestDox('Lager sind Bereiche mit eigenem Aussehen; Fachbereiche bleiben im DPSG-Design')]
    public function test_lager_helper(): void
    {
        $this->assertArrayHasKey('scoutergy', Lager::alle());
        $this->assertTrue(Lager::ist('scoutergy'));
        $this->assertFalse(Lager::ist('bolivien'));
        $this->assertFalse(Lager::ist(null));
        $this->assertSame('lager lager-scoutergy', Lager::klasse('scoutergy'));
        $this->assertSame('', Lager::klasse('bolivien'));
        $this->assertSame(url('/dioezesanlager'), Lager::uebersichtUrl());
    }

    #[TestDox('Migration legt Übersicht, Seiten, Beiträge mit alten Daten und Bilder an – ohne Kontaktdaten')]
    public function test_migration_legt_archiv_an(): void
    {
        $this->migrieren();

        $uebersicht = Page::query()->where('slug', 'dioezesanlager')->firstOrFail();
        $this->assertSame(Page::query()->where('slug', 'dioezesanverband')->value('id'), $uebersicht->parent_id);
        $this->assertTrue($uebersicht->show_in_nav);

        $this->assertSame(['scoutergy', 'scoutergy-faq', 'scoutergy-programm', 'scoutergy-team'],
            Page::query()->where('section', 'scoutergy')->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(6, Post::query()->where('section', 'scoutergy')->count());
        $this->assertSame('2021-11-20', Post::query()->where('slug', 'scoutergy-go-fuer-das-dioezesanlager')->first()->published_at->toDateString());
        $this->assertSame(12, Media::query()->where('path', 'like', 'media/scoutergy-%')->count());
        $this->assertFileExists($this->public.'/uploads/media/scoutergy-logo.png');

        $alles = Page::query()->where('section', 'scoutergy')->pluck('body')->implode('').Post::query()->where('section', 'scoutergy')->pluck('body')->implode('');
        $this->assertStringNotContainsString('@scoutergy', $alles);
        $this->assertStringNotContainsString('wp-content', $alles);
        $this->assertStringNotContainsString('Kraus', $alles);

        // Zweiter Lauf ändert nichts.
        $this->lauf();
        $this->assertSame(6, Post::query()->where('section', 'scoutergy')->count());
        $this->assertSame(12, Media::query()->where('path', 'like', 'media/scoutergy-%')->count());
    }

    #[TestDox('Lagerseite, Unterseiten, Beiträge und Übersicht zeigen Aussehen und Archivhinweis')]
    public function test_seiten_im_lager_design(): void
    {
        $this->migrieren();

        $this->get('/scoutergy')->assertOk()
            ->assertSee('Scoutergy!')
            ->assertSee('class="flex-1 lager lager-scoutergy"', false)
            ->assertSee('8.–11. Juni 2023')
            ->assertSee('teaser-720.mp4')
            ->assertSee('Rund ums Lager')
            ->assertSee('Häufige Fragen')
            ->assertSee('Aus der Lagerzeit')
            ->assertSee('Der Georgsbote ist raus!')
            ->assertSee('liegt zurück');

        $this->get('/scoutergy-faq')->assertOk()
            ->assertSee('lager lager-scoutergy', false)
            ->assertSee('<summary>Gibt es ein Maskottchen?</summary>', false)
            ->assertSee('Alle Diözesanlager');

        $this->get('/scoutergy-programm')->assertOk()->assertSee('class="galerie not-prose"', false);

        $this->get('/aktuelles/scoutergy-teaser')->assertOk()
            ->assertSee('lager lager-scoutergy', false)
            ->assertSee('aria-label="Scoutergy"', false)
            ->assertSee('<video', false);

        $this->get('/scoutergy/aktuelles')->assertOk()->assertSee('Beiträge: Scoutergy');

        $this->get('/dioezesanlager')->assertOk()
            ->assertSee('FroLeiLa des DV Trier 2023')
            ->assertSee('Bundeszentrum Westernohe')
            ->assertSee('href="'.url('/scoutergy').'"', false);
    }

    #[TestDox('Fachbereiche bleiben unverändert (kein Lager-Design, kein Archivhinweis)')]
    public function test_fachbereich_unveraendert(): void
    {
        $this->page('bolivien', ['title' => 'Bolivienpartnerschaft', 'section' => 'bolivien']);
        $this->page('bolivien-projekt', ['title' => 'Schulprojekt', 'section' => 'bolivien']);

        $this->get('/bolivien')->assertOk()->assertDontSee('lager-scoutergy')->assertDontSee('liegt zurück');
        $this->get('/bolivien-projekt')->assertOk()->assertSee('fach-internationales')->assertDontSee('lager-');
    }
}
