<?php

namespace Tests\Feature\Content;

use App\Models\Download;
use App\Models\Event;
use App\Models\GlossaryTerm;
use App\Models\Page;
use App\Models\Post;
use App\Support\Ausbau;
use App\Support\Features;
use App\Support\Suche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Suche auf der Website (docs/plan-ehrenamt.md Nr. 3).
 */
class SucheTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Findet veröffentlichte Seiten, Beiträge, Veranstaltungen und Begriffe; Titel vor Text; Entwürfe nicht; Auszug HTML-sicher mit Markierung')]
    public function test_suche(): void
    {
        Page::query()->create(['slug' => 'pfingstlager-info', 'title' => 'Pfingstlager', 'body' => '<p>Alles zum Lager an Pfingsten.</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        Page::query()->create(['slug' => 'packliste', 'title' => 'Packliste', 'body' => '<p>Für das Pfingstlager brauchst du <b>Schlafsack</b> &amp; Isomatte.</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        Page::query()->create(['slug' => 'geheim', 'title' => 'Pfingstlager Entwurf', 'body' => '<p>x</p>', 'status' => 'draft']);
        Post::query()->create(['title' => 'Rückblick', 'slug' => 'rueckblick', 'teaser' => 'Das Pfingstlager war toll', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        Event::query()->create(['title' => 'Pfingstlager 2027', 'slug' => 'pfingstlager-2027', 'status' => 'open', 'starts_at' => now()->addMonths(6)]);
        GlossaryTerm::query()->create(['term' => 'Kohte', 'explanation' => 'Schwarzzelt, z. B. für das Pfingstlager.']);

        $treffer = Suche::finden('pfingstlager');
        $titel = $treffer->pluck('titel')->all();
        $this->assertNotContains('Pfingstlager Entwurf', $titel);
        $this->assertContains('Packliste', $titel);
        $this->assertContains('Kohte', $titel);
        $this->assertSame('Pfingstlager', $treffer->first(fn ($t) => $t['art'] === 'Seite')['titel'], 'Titeltreffer vor Texttreffer');
        if (Ausbau::termine()) {
            $this->assertContains('Rückblick', $titel);
            $this->assertContains('Pfingstlager 2027', $titel);
        }
        $packliste = $treffer->firstWhere('titel', 'Packliste');
        $this->assertSame(url('/packliste'), $packliste['url']);
        $this->assertStringContainsString('<mark>Pfingstlager</mark>', $packliste['auszug']);
        $this->assertStringContainsString('Schlafsack &amp; Isomatte', $packliste['auszug']);

        // Mehrere Wörter: alle müssen vorkommen.
        $this->assertSame(['Packliste'], Suche::finden('Pfingstlager Isomatte')->pluck('titel')->all());
        // LIKE-Platzhalter sind normale Zeichen.
        $this->assertSame([], Suche::finden('%_%')->all());
    }

    #[TestDox('Seite /suche: Formular, Treffer, zu kurze Eingabe, kein Index; Link im Kopf')]
    public function test_seite(): void
    {
        Page::query()->create(['slug' => 'packliste', 'title' => 'Packliste', 'body' => '<p>Schlafsack <script>alert(1)</script></p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get('/suche')->assertOk()->assertSee('name="q"', false)->assertDontSee('Treffer');
        $this->get('/suche?q=schlafsack')->assertOk()->assertSee('1 Treffer')->assertSee('Packliste')->assertSee('<mark>Schlafsack</mark>', false)
            ->assertDontSee('<script>alert(1)', false)->assertSee('noindex', false);
        $this->get('/suche?q=a')->assertOk()->assertSee('mindestens zwei Buchstaben');
        GlossaryTerm::query()->create(['term' => 'Schlafsack', 'explanation' => 'Zum Schlafen im Zelt.']);
        Download::query()->create(['title' => 'Packliste Schlafsack', 'category' => 'formulare', 'file_path' => 'x.pdf', 'file_name' => 'x.pdf', 'published' => true, 'rights_confirmed' => true]);
        $alle = $this->get('/suche?q=schlafsack')->assertOk();
        if (Features::enabled(Features::DOWNLOADS)) {
            $alle->assertSee('aria-label="Bereich"', false)->assertSee('bereich=downloads', false);
            $this->get('/suche?q=schlafsack&bereich=downloads')->assertSee('Packliste Schlafsack')->assertDontSee('Zum Schlafen im Zelt');
        }
        $this->get('/suche?q=gibtesnicht')->assertOk()->assertSee('Keine Treffer');
        $this->get('/packliste')->assertOk()->assertSee(route('suche'), false);
    }
}
