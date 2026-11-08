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

    #[TestDox('Weitere Archive: Schongse, futurscout, Scoutgames mit eigenem Aussehen, Übersicht nach Jahr')]
    public function test_weitere_archive(): void
    {
        $this->migrieren();
        (require base_path('instanzen/dv-trier/migrations/2026_11_07_120000_dioezesanlager_archiv_2004_2011_2020.php'))->up();

        $this->assertSame(['scoutergy', 'scoutgames', 'gastfreundschaft', 'futurscout', 'schongse'], array_keys(Lager::alle()));
        foreach (['schongse' => 'Das Weltdorf', 'futurscout' => 'abheben zu neuen Planeten', 'scoutgames' => 'Pfadfinder spielen (immer noch) gern'] as $key => $motto) {
            $this->get('/'.$key)->assertOk()
                ->assertSee('class="flex-1 lager lager-'.$key.'"', false)
                ->assertSee($motto)
                ->assertSee('liegt zurück');
        }
        $this->get('/schongse-programm')->assertOk()->assertSee('Kontinentetag')->assertSee('lager-schongse', false);

        // Keine Kontaktdaten der damaligen Teams.
        $texte = Page::query()->whereIn('section', ['schongse', 'futurscout', 'scoutgames'])->pluck('body')->implode('');
        $this->assertDoesNotMatchRegularExpression('/@|Tel\.|\d{4,5}\/\d/', $texte);

        $uebersicht = $this->get('/dioezesanlager')->assertOk()->getContent();
        $positionen = array_map(fn ($t) => strpos($uebersicht, $t), ['Scoutergy!', 'Scoutgames 2020', 'futurscout 2011', 'Schongse 2004']);
        $sortiert = $positionen;
        sort($sortiert);
        $this->assertSame($sortiert, $positionen);
    }

    #[TestDox('Archiv 2015: Gast>>FREUNDschaft mit eigenem Aussehen, breitem Logo und ohne Namen des Teams')]
    public function test_archiv_2015(): void
    {
        $this->migrieren();
        (require base_path('instanzen/dv-trier/migrations/2026_11_07_120000_dioezesanlager_archiv_2004_2011_2020.php'))->up();
        $migration = require base_path('instanzen/dv-trier/migrations/2026_11_08_120000_dioezesanlager_archiv_2015.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, Page::query()->where('slug', 'gastfreundschaft')->count());
        $this->get('/gastfreundschaft')->assertOk()
            ->assertSee('class="flex-1 lager lager-gastfreundschaft"', false)
            ->assertSee('Gast&gt;&gt;FREUNDschaft', false)
            ->assertSee('Ferschweiler')
            ->assertSee('Stufentag')
            ->assertSee('liegt zurück');

        $text = Page::query()->where('slug', 'gastfreundschaft')->value('body');
        $this->assertDoesNotMatchRegularExpression('/@|Tel\.|\d{4,5}\/\d/', $text);
        foreach (['Krings', 'Postler', 'Laskewicz', 'Gambalonga', 'Hemgesberg', 'Kimpel', 'Basenach'] as $name) {
            $this->assertStringNotContainsString($name, $text);
        }

        $uebersicht = $this->get('/dioezesanlager')->assertOk()->getContent();
        $this->assertLessThan(strpos($uebersicht, 'futurscout 2011'), strpos($uebersicht, 'Gast&gt;&gt;FREUNDschaft 2015'));
        $this->assertGreaterThan(strpos($uebersicht, 'Scoutgames 2020'), strpos($uebersicht, 'Gast&gt;&gt;FREUNDschaft 2015'));
    }

    #[TestDox('Scoutgames im Aussehen der alten Seite: Schriftzug, Wappen, Zelte unten, kein Laufband')]
    public function test_scoutgames_wie_alte_seite(): void
    {
        $this->migrieren();
        (require base_path('instanzen/dv-trier/migrations/2026_11_07_120000_dioezesanlager_archiv_2004_2011_2020.php'))->up();

        $this->get('/scoutgames')->assertOk()
            ->assertSee('instanz/images/scoutgames/wappen.webp', false)
            ->assertSee('instanz/images/scoutgames/zelte.webp', false)
            ->assertSee('class="lager-fuss', false)
            ->assertDontSee('class="lager-laufband', false);
        foreach (['logo.png', 'logo.webp', 'wappen.png', 'wappen.webp', 'zelte.png', 'zelte.webp', 'rasen.png'] as $datei) {
            $this->assertFileExists(base_path('instanzen/dv-trier/public/images/scoutgames/'.$datei));
        }

        // Andere Lager ohne Wappen und Fußbild.
        $this->get('/futurscout')->assertOk()->assertDontSee('class="lager-fuss', false)->assertSee('class="lager-laufband', false);
    }

    #[TestDox('Scoutgames: Hintergrund als digitaler Ersatz für das ausgefallene Diözesanlager; Bearbeitetes bleibt')]
    public function test_scoutgames_ersatzveranstaltung(): void
    {
        $this->migrieren();
        (require base_path('instanzen/dv-trier/migrations/2026_11_07_120000_dioezesanlager_archiv_2004_2011_2020.php'))->up();
        $migration = require base_path('instanzen/dv-trier/migrations/2026_11_08_130000_scoutgames_ersatzveranstaltung.php');
        $migration->up();
        $migration->up();

        $this->get('/scoutgames')->assertOk()
            ->assertSee('Wegen der Corona-Pandemie fiel es aus')
            ->assertSee('digitale Ersatzveranstaltung')
            ->assertSee('Digitaler Ersatz für das Diözesanlager 2020');
        $this->assertSame(1, substr_count((string) Page::query()->where('slug', 'scoutgames')->value('body'), 'Corona'));
        $this->assertStringContainsString('ausgefallene Diözesanlager', (string) Page::query()->where('slug', 'scoutgames')->value('seo_description'));

        Page::query()->where('slug', 'scoutgames')->update(['body' => '<p>Eigener Text.</p>']);
        $migration->up();
        $this->assertSame('<p>Eigener Text.</p>', Page::query()->where('slug', 'scoutgames')->value('body'));
    }
}
