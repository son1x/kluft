<?php

namespace Tests\Feature\Content;

use App\Models\Gruppierung;
use App\Models\Page;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Instanzen\DvTrier\Seeders\Inhalt\TidyStagePageBodies;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Stufenübersicht und Stufenseiten (Pflichtenheft 6.1, 7.1).
 */
class StagePagesTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, string $title, ?string $stage = null, string $body = '<p>Text</p>'): Page
    {
        return Page::query()->create([
            'title' => $title, 'slug' => $slug, 'stage' => $stage, 'body' => $body,
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);
    }

    #[Group('dv-trier')]

    #[TestDox('Stufenseite zeigt Kopfband, Referent*innen, Stämme und Nachbarstufen')]
    public function test_stage_page_shows_hero_referents_staemme_and_neighbours(): void
    {
        $this->page('stufen', 'Die Stufen');
        $this->page('stufe-biber', 'Biber', 'biber');
        $this->page('stufe-woelflinge', 'Wölflinge', 'woelflinge', '<p>Wölflinge spielen.</p><ul><li>Spiel</li></ul>');
        $this->page('stufe-jupfi', 'Jungpfadfinder*innen', 'jupfi');

        Person::query()->create(['gremium' => 'leitung', 'name' => 'Heike Franz', 'role' => 'Referentin der Wölflingsstufe', 'stage' => 'woelflinge', 'consent' => true]);
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Ohne Zustimmung', 'stage' => 'woelflinge', 'consent' => false]);

        // Die Stämme-Daten kommen per Migration mit; nur der Zuwachs zählt.
        $before = Gruppierung::query()->where('typ', 'stamm')->where('active', true)
            ->whereExists(fn ($q) => $q->from('gruppierung_stufen')->whereColumn('gruppierung_id', 'gruppierungen.id')->where('stufe', 'woelflinge'))
            ->count();

        foreach (['a' => ['woelflinge'], 'b' => ['woelflinge', 'biber'], 'c' => ['biber']] as $slug => $stufen) {
            Gruppierung::query()->create(['typ' => 'stamm', 'name' => "Stamm {$slug}", 'slug' => $slug])->syncStufen($stufen);
        }

        $this->get('/stufe-woelflinge')->assertOk()
            ->assertSee('stufe-woelflinge', false)
            ->assertSee('6 bis 10 Jahre')
            ->assertSee(config('dpsg.stages.woelflinge.tagline'))
            ->assertSee('Wölflinge spielen.')
            ->assertSee('Heike Franz')
            ->assertDontSee('Ohne Zustimmung')
            ->assertSee(($before + 2).' Stämmen</strong>', false)
            ->assertSee('/bezirke-staemme?stufe=woelflinge', false)
            ->assertSeeInOrder(['Jüngere Stufe', 'Biber', 'Nächste Stufe', 'Jungpfadfinder*innen'])
            ->assertSee('Die Stufen');
    }

    #[TestDox('Stufenübersicht zeigt Altersleiste und Kacheln in pädagogischer Reihenfolge')]
    public function test_overview_shows_age_bar_and_cards_in_pedagogical_order(): void
    {
        $this->page('stufen', 'Die Stufen', null, '<p>Einleitung</p>');
        $this->page('stufe-rover', 'Rover', 'rover', '<p>Rover packen an.</p>');
        $this->page('stufe-biber', 'Biber', 'biber', '<p>Biber entdecken.</p>');

        $this->get('/stufen')->assertOk()
            ->assertSee('Einleitung')
            ->assertSee('Welche Stufe passt zu welchem Alter?')
            ->assertSeeInOrder(['Biber entdecken.', 'Rover packen an.'])
            ->assertSee(config('dpsg.stages.rover.tagline'));
    }

    #[TestDox('Doppelte Texte werden nur entfernt, wenn sie unverändert sind')]
    public function test_migration_removes_duplicated_age_line_and_stage_list_only_when_unchanged(): void
    {
        $biber = $this->page('stufe-biber', 'Biber', 'biber', "<p><strong>4 bis 7 Jahre</strong> – neugierig durch die Welt gehen</p>\n<p>Ab dem Alter von vier Jahren …</p>");
        $edited = $this->page('stufe-rover', 'Rover', 'rover', "<p>Neu geschrieben.</p>\n<p><strong>15 bis 20 Jahre</strong> – bleibt, weil nicht am Anfang</p>");
        $overview = $this->page('stufen', 'Die Stufen', null, "<p>Intro</p>\n<ul>\n"
            .collect(['biber', 'woelflinge', 'jupfi', 'pfadi', 'rover'])->map(fn ($s) => "<li><strong><a href=\"/stufe-{$s}\">X</a></strong> (1 bis 2 Jahre) – y</li>")->implode("\n")
            ."\n</ul>");

        (new TidyStagePageBodies)->run();

        $this->assertSame('<p>Ab dem Alter von vier Jahren …</p>', $biber->fresh()->body);
        $this->assertSame($edited->body, $edited->fresh()->body);
        $this->assertSame('<p>Intro</p>', $overview->fresh()->body);
    }
}
