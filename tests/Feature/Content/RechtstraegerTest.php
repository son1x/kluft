<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Models\Person;
use App\Models\Redirect;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\RequiredPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Rechtsträgerstruktur (Landesamt St. Georg e. V., dpsg trevirensis gGmbH,
 * Stiftung, Freunde & Förderer) unter „Diözesanverband“ und Impressum.
 */
class RechtstraegerTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Rechtsträger steht im Menü unter „Diözesanverband“; Seite zeigt Struktur und Unterseiten')]
    public function test_menu_and_page(): void
    {
        $this->seed([RequiredPagesSeeder::class, DemoContentSeeder::class]);

        $html = $this->get('/kalender')->assertOk()->getContent();
        $desktop = substr($html, strpos($html, 'aria-label="Hauptnavigation"'));
        $this->assertMatchesRegularExpression('#Diözesanverband.*Diözesanleitung.*Georgsbrief.*Rechtsträger.*Die Stufen#s', $desktop);

        $this->get('/rechtstraeger')->assertOk()
            ->assertSee('So hängt alles zusammen')
            ->assertSeeInOrder(['DPSG Diözesanverband Trier', 'Landesamt St. Georg e. V.', 'dpsg trevirensis gemeinnützige GmbH', 'Stiftung DPSG Diözesanverband Trier', 'Freunde &amp; Förderer'], false)
            ->assertSee('href="'.url('/stiftung').'"', false)
            ->assertSee('Geschäftsführer des Rechtsträgers ist Marc Basenach');

        $this->get('/stiftung')->assertOk()->assertSee('Stiftung GLAUBEN LEBEN')->assertSee('Rechtsträger');
        $this->get('/freunde-und-foerderer')->assertOk()->assertSee('Finanzamt Koblenz');
    }

    #[TestDox('Impressum nennt Marc Basenach als inhaltlich Verantwortlichen und den Rechtsträger')]
    public function test_impressum(): void
    {
        $this->seed(RequiredPagesSeeder::class);

        $this->get('/impressum')->assertOk()
            ->assertSeeInOrder(['Rechtsträger: Landesamt St. Georg e. V.', 'Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV', 'Marc Basenach'])
            ->assertDontSee('Name, Anschrift wie oben');
    }

    #[TestDox('Migration ergänzt bestehende Installationen: Seiten, Weiterleitungen, Rolle und Impressum – bearbeitete Inhalte bleiben')]
    public function test_migration_updates_existing_installation(): void
    {
        $dv = Page::query()->create(['title' => 'Diözesanverband', 'slug' => 'dioezesanverband', 'body' => '<p>DV</p>', 'status' => 'published', 'published_at' => now(), 'show_in_nav' => true]);
        $impressum = Page::query()->create(['title' => 'Impressum', 'slug' => 'impressum', 'status' => 'published', 'published_at' => now(),
            'body' => '<h2>Verantwortlich</h2><p>[TODO: Name, Anschrift wie oben]</p>']);
        $marc = Person::query()->create(['gremium' => 'buero', 'name' => 'Marc Basenach', 'role' => 'Geschäftsführender Bildungsreferent', 'consent' => true]);
        $andere = Person::query()->create(['gremium' => 'buero', 'name' => 'Andere Person', 'role' => 'Geschäftsführender Bildungsreferent', 'consent' => true]);

        $migration = require database_path('migrations/2026_10_03_090000_rechtstraeger_pages_and_impressum.php');
        $migration->up();
        $migration->up(); // wiederholbar

        $rt = Page::query()->where('slug', 'rechtstraeger')->sole();
        $this->assertSame($dv->id, $rt->parent_id);
        $this->assertTrue($rt->show_in_nav);
        $this->assertSame(['stiftung', 'freunde-und-foerderer'], $rt->navChildren()->pluck('slug')->all());
        $this->assertSame('/stiftung', Redirect::query()->where('from_path', '/dioezesanverband/rechtstraeger/stiftung')->value('to_path'));
        $this->get('/dioezesanverband/rechtstraeger/rechtstraegerstruktur/')->assertRedirect('/rechtstraeger')->assertStatus(301);

        $this->assertStringContainsString('Geschäftsführer des Rechtsträgers', $marc->fresh()->role);
        $this->assertSame('Geschäftsführender Bildungsreferent', $andere->fresh()->role);
        $this->assertStringContainsString('Marc Basenach', $impressum->fresh()->body);

        // Redaktionell geänderte Inhalte werden nicht überschrieben.
        $impressum->update(['body' => '<p>Eigener Text</p>']);
        $rt->update(['body' => '<p>Eigene Fassung</p>']);
        $migration->up();
        $this->assertSame('<p>Eigener Text</p>', $impressum->fresh()->body);
        $this->assertSame('<p>Eigene Fassung</p>', $rt->fresh()->body);
    }
}
