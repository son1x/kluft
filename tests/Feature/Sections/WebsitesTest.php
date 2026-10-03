<?php

namespace Tests\Feature\Sections;

use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Support\Websites;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Mehrere Websites in einem CMS: Hauptseite, bolivienpartnerschaft.de und
 * beraten-online.de (Pflichtenheft 4.2, 6.2, 6.3).
 */
class WebsitesTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, array $attrs = []): Page
    {
        return Page::query()->create(['title' => ucfirst($slug), 'slug' => $slug, 'body' => "<p>Inhalt {$slug}</p>", 'status' => 'published', 'published_at' => now()->subDay(), ...$attrs]);
    }

    #[TestDox('Websites: Hauptseite plus jede Sektion mit eigener Domain; Inhalte ohne eigene Domain zählen zur Hauptseite')]
    public function test_websites(): void
    {
        $this->assertSame(['hauptseite' => 'dpsg-trier.de', 'bolivien' => 'bolivienpartnerschaft.de', 'beratung' => 'beraten-online.de'], Websites::all());
        $this->assertSame('beraten-online.de', Websites::domainFor('beratung'));
        $this->assertSame('dpsg-trier.de', Websites::domainFor('inklusion'));
        $this->assertSame('dpsg-trier.de', Websites::domainFor(null));
    }

    #[TestDox('Backend: Reiter je Website über Seiten und Beiträgen, Spalte „Website“')]
    public function test_backend_tabs(): void
    {
        $this->page('ueber-uns');
        $this->page('inklusion', ['section' => 'inklusion']);
        $this->page('bolivien', ['section' => 'bolivien']);
        $this->page('beratung', ['section' => 'beratung']);
        $this->page('coaching', ['section' => 'beratung']);
        Post::query()->create(['title' => 'Beratung neu', 'slug' => 'beratung-neu', 'teaser' => '', 'body' => '<p>x</p>', 'section' => 'beratung', 'scope' => 'section', 'status' => 'draft']);
        Post::query()->create(['title' => 'Allgemein', 'slug' => 'allgemein', 'teaser' => '', 'body' => '<p>x</p>', 'status' => 'draft']);

        Filament::setCurrentPanel('admin');
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@dpsg-trier.de', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $this->actingAs($admin);

        Livewire::test(ListPages::class)
            ->assertSee('beraten-online.de')
            ->set('activeTab', 'beratung')
            ->assertCanSeeTableRecords(Page::query()->where('section', 'beratung')->get())
            ->assertCanNotSeeTableRecords(Page::query()->whereIn('slug', ['ueber-uns', 'bolivien', 'inklusion'])->get())
            ->set('activeTab', 'hauptseite')
            ->assertCanSeeTableRecords(Page::query()->whereIn('slug', ['ueber-uns', 'inklusion'])->get())
            ->assertCanNotSeeTableRecords(Page::query()->whereIn('slug', ['bolivien', 'beratung', 'coaching'])->get())
            ->assertTableColumnStateSet('website', 'dpsg-trier.de', Page::query()->where('slug', 'inklusion')->first());

        Livewire::test(ListPosts::class)
            ->set('activeTab', 'beratung')
            ->assertCanSeeTableRecords(Post::query()->where('slug', 'beratung-neu')->get())
            ->assertCanNotSeeTableRecords(Post::query()->where('slug', 'allgemein')->get())
            ->assertTableColumnStateSet('website', 'beraten-online.de', Post::query()->where('slug', 'beratung-neu')->first());
    }

    #[TestDox('beraten-online.de zeigt die Startseite der Beratung; alte Unterseiten-Adressen funktionieren weiter')]
    public function test_beraten_online_domain(): void
    {
        config(['app.url' => 'https://www.dpsg-trier.de']);
        $this->page('start', ['title' => 'Startseite DV']);
        $this->page('beratung', ['title' => 'BeratungsPower & Coaching', 'section' => 'beratung']);
        $this->page('coaching', ['title' => 'Coaching', 'section' => 'beratung']);

        $this->get('https://www.beraten-online.de/')->assertOk()->assertSee('Inhalt beratung')->assertDontSee('Startseite DV')
            ->assertSee('aria-label="BeratungsPower &amp; Coaching"', false)
            ->assertSee('<link rel="canonical" href="https://www.dpsg-trier.de/beratung" />', false);
        // Alte WordPress-Adresse mit Schrägstrich (auf dem Server leitet .htaccess zusätzlich um).
        $this->get('https://beraten-online.de/coaching/')->assertOk()->assertSee('Inhalt coaching');
        $this->get('https://beraten-online.de/coaching')->assertOk()->assertSee('Inhalt coaching')
            ->assertSee('<link rel="canonical" href="https://www.dpsg-trier.de/coaching" />', false);
        // Unter der Hauptadresse kein zusätzlicher Verweis.
        $this->get('https://www.dpsg-trier.de/coaching')->assertOk()->assertDontSee('rel="canonical"', false);
    }

    #[TestDox('BeratungsPower & Coaching ist ein Serviceangebot: Kennzeichnung im Kopf, nicht in der Fachbereichs-Übersicht')]
    public function test_service_offer_not_in_fachbereiche(): void
    {
        $this->page('fachbereiche', ['title' => 'Fachbereiche']);
        $this->page('inklusion', ['title' => 'Inklusion', 'section' => 'inklusion']);
        $this->page('beratung', ['title' => 'BeratungsPower & Coaching', 'section' => 'beratung']);

        $this->get('/beratung')->assertOk()->assertSee('Serviceangebot · DPSG Diözesanverband Trier');
        $this->get('/inklusion')->assertOk()->assertSee('Fachbereich · DPSG Diözesanverband Trier');

        $main = strstr($this->get('/fachbereiche')->assertOk()->getContent(), '<main');
        $this->assertStringContainsString('Inklusion', $main);
        $this->assertStringNotContainsString('BeratungsPower', $main);
    }
}
