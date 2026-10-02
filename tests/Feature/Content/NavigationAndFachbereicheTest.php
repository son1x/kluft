<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Hauptmenü mit Unterpunkten (Stufen, Fachbereiche), Fachbereichsübersicht
 * und Diözesanarbeitskreise auf den Stufenseiten.
 */
class NavigationAndFachbereicheTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, string $title, array $attrs = []): Page
    {
        return Page::query()->create([
            'title' => $title, 'slug' => $slug, 'body' => '<p>Text zu '.$title.'</p>',
            'status' => 'published', 'published_at' => now()->subDay(), ...$attrs,
        ]);
    }

    #[TestDox('Hauptmenü zeigt Stufen und Fachbereiche mit Unterpunkten in der richtigen Reihenfolge')]
    public function test_main_menu_has_submenus_in_order(): void
    {
        $stufen = $this->page('stufen', 'Die Stufen', ['show_in_nav' => true, 'nav_sort' => 20]);
        $fach = $this->page('fachbereiche', 'Fachbereiche', ['show_in_nav' => true, 'nav_sort' => 30]);
        $this->page('stufe-rover', 'Rover', ['stage' => 'rover', 'parent_id' => $stufen->id, 'show_in_nav' => true, 'nav_sort' => 50]);
        $this->page('stufe-biber', 'Biber', ['stage' => 'biber', 'parent_id' => $stufen->id, 'show_in_nav' => true, 'nav_sort' => 10]);
        $this->page('inklusion', 'Inklusion', ['section' => 'inklusion', 'parent_id' => $fach->id, 'show_in_nav' => true, 'nav_sort' => 30]);
        $this->page('bolivien', 'Bolivienpartnerschaft', ['section' => 'bolivien', 'parent_id' => $fach->id, 'show_in_nav' => true, 'nav_sort' => 10]);
        $this->page('versteckt', 'Nicht im Menü', ['parent_id' => $fach->id, 'show_in_nav' => false]);

        $html = $this->get('/kalender')->assertOk()->getContent();
        $desktop = substr($html, strpos($html, 'aria-label="Hauptnavigation"'));

        $this->assertMatchesRegularExpression('#Die Stufen.*Übersicht Die Stufen.*Biber.*Rover.*Fachbereiche.*Bolivienpartnerschaft.*Inklusion#s', $desktop);
        $this->assertStringNotContainsString('Nicht im Menü', $desktop);
        // Unterseiten erscheinen nicht zusätzlich als eigener Hauptpunkt.
        $this->assertSame(1, preg_match_all('/\bRover\b/', strstr($desktop, '</nav>', true)));
    }

    #[TestDox('Fachbereichsübersicht zeigt alle Fachbereiche mit Ansprechpersonen')]
    public function test_fachbereiche_overview_lists_sections_with_contacts(): void
    {
        $fach = $this->page('fachbereiche', 'Fachbereiche', ['show_in_nav' => true]);
        $this->page('bolivien', 'Bolivienpartnerschaft', ['section' => 'bolivien', 'parent_id' => $fach->id, 'nav_sort' => 10]);
        $this->page('internationale-gerechtigkeit', 'Internationale Gerechtigkeit', ['section' => 'internationale-gerechtigkeit', 'parent_id' => $fach->id, 'nav_sort' => 20]);
        $this->page('inklusion', 'Inklusion', ['section' => 'inklusion', 'parent_id' => $fach->id, 'nav_sort' => 30]);
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Bianca Boltze', 'section' => 'inklusion', 'badge' => 'inklusion', 'consent' => true]);

        $this->get('/fachbereiche')->assertOk()
            ->assertSeeInOrder(['Bolivienpartnerschaft', 'Internationale Gerechtigkeit', 'Inklusion', 'Bianca Boltze'])
            ->assertSee('fach-inklusion', false);

        $this->get('/inklusion')->assertOk()
            ->assertSee('fach-inklusion', false)
            ->assertSee('Bianca Boltze')
            ->assertSee('Noch keine Meldungen aus dem Fachbereich Inklusion.');
    }

    #[TestDox('Stufenseite zeigt den DAK: Referent*innen und Mitglieder, nur mit Einwilligung')]
    public function test_stage_page_shows_dak(): void
    {
        $this->page('stufe-jupfi', 'Jungpfadfinder*innen', ['stage' => 'jupfi']);
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Silas Weiß', 'role' => 'Referent der Jungpfadfinderstufe', 'stage' => 'jupfi', 'consent' => true]);
        $member = Person::query()->create(['gremium' => 'dak', 'name' => 'Mara Muster', 'role' => 'DAK-Mitglied', 'stage' => 'jupfi', 'email' => 'dak-jupfi@example.org', 'consent' => true]);
        Person::query()->create(['gremium' => 'dak', 'name' => 'Ohne Freigabe', 'stage' => 'jupfi', 'consent' => false]);
        Person::query()->create(['gremium' => 'dak', 'name' => 'Andere Stufe', 'stage' => 'rover', 'consent' => true]);

        $this->get('/stufe-jupfi')->assertOk()
            ->assertSee('Der Diözesanarbeitskreis')
            ->assertSeeInOrder(['Stufenreferent*innen', 'Silas Weiß', 'Im Arbeitskreis', 'Mara Muster'])
            ->assertSee('mailto:dak-jupfi@example.org', false)
            ->assertDontSee('Ohne Freigabe')
            ->assertDontSee('Andere Stufe');

        $this->assertSame('DAK Jungpfadfinder*innen', $member->gremiumLabel());
        $this->assertStringEndsWith('/stufe-jupfi#person-mara-muster', $member->pageUrl());
    }

    #[TestDox('Menü-Migration ordnet Stufen und Bolivien unter und legt fehlende Fachbereiche an')]
    public function test_migration_builds_menu_structure_without_overwriting(): void
    {
        $stufen = $this->page('stufen', 'Die Stufen', ['show_in_nav' => true, 'nav_sort' => 20]);
        $biber = $this->page('stufe-biber', 'Biber', ['stage' => 'biber']);
        $bolivien = $this->page('bolivien', 'Bolivienpartnerschaft', ['section' => 'bolivien', 'show_in_nav' => true, 'nav_sort' => 40]);
        $inklusion = $this->page('inklusion', 'Inklusion', ['section' => 'inklusion', 'body' => '<p>Eigener Text</p>']);
        $bianca = Person::query()->create(['gremium' => 'leitung', 'name' => 'Bianca Boltze', 'badge' => 'inklusion', 'consent' => true]);

        DB::table('migrations')->where('migration', '2026_10_02_110000_menu_stufen_and_fachbereiche')->delete();
        (require database_path('migrations/2026_10_02_110000_menu_stufen_and_fachbereiche.php'))->up();

        $fach = Page::query()->where('slug', 'fachbereiche')->firstOrFail();
        $this->assertTrue($fach->show_in_nav);
        $this->assertSame($stufen->id, $biber->fresh()->parent_id);
        $this->assertTrue($biber->fresh()->show_in_nav);
        $this->assertSame($fach->id, $bolivien->fresh()->parent_id);
        $this->assertSame('<p>Eigener Text</p>', $inklusion->fresh()->body);
        $this->assertSame($fach->id, Page::query()->where('slug', 'internationale-gerechtigkeit')->value('parent_id'));
        $this->assertSame('inklusion', $bianca->fresh()->section);
    }
}
