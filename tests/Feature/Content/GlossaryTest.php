<?php

namespace Tests\Feature\Content;

use App\Models\GlossaryTerm;
use App\Models\Page;
use App\Services\Glossary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Begriffs-Hover: Abkürzungen im Text werden beim ersten Vorkommen erklärt.
 */
class GlossaryTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Erstes Vorkommen eines Begriffs wird erklärt – nicht in Links, Überschriften oder Wortteilen')]
    public function test_first_occurrence_is_annotated_outside_links_and_headings(): void
    {
        GlossaryTerm::query()->create(['term' => 'DAK', 'explanation' => 'Diözesanarbeitskreis <Stufe> & mehr']);
        GlossaryTerm::query()->create(['term' => 'DL', 'explanation' => 'Diözesanleitung']);

        $html = app(Glossary::class)->annotate(
            '<h2>Der DAK stellt sich vor</h2><p><a href="/x">DAK-Seite</a> Der DAK trifft sich. Der DAK plant. DLRG ist kein DL.</p>'
        );

        $this->assertSame(1, substr_count($html, 'class="glossar"') - substr_count($html, 'data-erklaerung="Diözesanleitung"'));
        $this->assertStringContainsString('<h2>Der DAK stellt sich vor</h2>', $html);
        $this->assertStringContainsString('<a href="/x">DAK-Seite</a> Der <abbr class="glossar" tabindex="0" data-erklaerung="Diözesanarbeitskreis &lt;Stufe&gt; &amp; mehr">DAK<span class="sr-only"> (Diözesanarbeitskreis &lt;Stufe&gt; &amp; mehr)</span></abbr> trifft sich. Der DAK plant.', $html);
        $this->assertStringContainsString('DLRG ist kein <abbr class="glossar"', $html);
    }

    #[TestDox('Seiten zeigen die Erklärung; Änderungen im Glossar wirken sofort')]
    public function test_pages_show_annotations_and_changes_apply_immediately(): void
    {
        Page::query()->create(['title' => 'Über uns', 'slug' => 'ueber-uns', 'body' => '<p>Der DAK lädt ein.</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get('/ueber-uns')->assertOk()->assertDontSee('class="glossar"', false);

        $term = GlossaryTerm::query()->create(['term' => 'DAK', 'explanation' => 'Diözesanarbeitskreis']);
        $this->get('/ueber-uns')->assertSee('data-erklaerung="Diözesanarbeitskreis"', false);

        $term->update(['explanation' => 'Arbeitskreis einer Stufe']);
        $this->get('/ueber-uns')->assertSee('data-erklaerung="Arbeitskreis einer Stufe"', false);
    }
}
