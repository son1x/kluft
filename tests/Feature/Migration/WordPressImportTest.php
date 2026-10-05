<?php

namespace Tests\Feature\Migration;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\User;
use App\Models\WpImportRun;
use App\Services\WordPress\MediaFetcher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * WordPress-Migration, Pflichtenheft 6.9 und Abnahmekriterium 15 (gegen
 * eine simulierte WordPress-API).
 */
#[Group('dv-trier')]
class WordPressImportTest extends TestCase
{
    use RefreshDatabase;

    private const WP = 'https://www.dpsg-trier.de';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['dpsg.wp_import.source' => self::WP]);
    }

    private function png(): string
    {
        $img = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function wpPosts(): array
    {
        return [
            [
                'id' => 101, 'date' => '2025-03-20T10:00:00', 'slug' => 'wir-leben-demokratie', 'link' => self::WP.'/wir-leben-demokratie/',
                'title' => ['rendered' => 'Wir leben Demokratie!'],
                'excerpt' => ['rendered' => '<p>Auch uns haben Stimmen erreicht [&hellip;]</p>'],
                'content' => ['rendered' => '<!-- wp:paragraph --><p class="wp-block-paragraph" style="color:red">Auch uns haben <strong>Stimmen</strong> erreicht.</p>'
                    .'<figure class="wp-block-image"><img src="'.self::WP.'/wp-content/uploads/2025/03/bild-300x200.png" alt="" class="wp-image-5" srcset="x 300w"/></figure>'
                    .'<p>Mehr im <a href="'.self::WP.'/georgsbrief-mai-2025/?utm_source=ig&amp;fbclid=abc">Georgsbrief</a> und im <a href="'.self::WP.'/wp-content/uploads/2025/03/info.pdf">Infoblatt</a>.</p>'
                    .'<div data-wp-interactive="core/file" class="wp-block-file"><object class="wp-block-file__embed" data="'.self::WP.'/wp-content/uploads/2025/03/info.pdf" type="application/pdf"></object><a href="'.self::WP.'/wp-content/uploads/2025/03/info.pdf">Infoblatt_DPSG</a><a href="'.self::WP.'/wp-content/uploads/2025/03/info.pdf" class="wp-block-file__button wp-element-button" download>Herunterladen</a></div>'
                    .'<iframe src="https://www.youtube.com/embed/51ri-zNfDnE"></iframe><script>alert(1)</script><p>&nbsp;</p>'],
                '_embedded' => ['author' => [['slug' => 'redaktion-alt', 'name' => 'Alte Redaktion']]],
            ],
            [
                'id' => 102, 'date' => '2025-05-19T09:00:00', 'slug' => 'georgsbrief-mai-2025', 'link' => self::WP.'/georgsbrief-mai-2025/',
                'title' => ['rendered' => 'Georgsbrief Mai 2025'],
                'excerpt' => ['rendered' => '<p>Der neue Georgsbrief ist da!</p>'],
                'content' => ['rendered' => '<p class="wp-block-paragraph">Der neue Georgsbrief ist da!</p>'],
            ],
            [
                'id' => 103, 'date' => '2024-06-08T09:00:00', 'slug' => 'scoutergy', 'link' => self::WP.'/scoutergy/',
                'title' => ['rendered' => 'Heute vor einem Jahr&#8230;Scoutergy'],
                'excerpt' => ['rendered' => ''],
                'content' => ['rendered' => '<p>Unser Abschlussvideo&#8230;</p>'],
            ],
        ];
    }

    private function fakeWordPress(): void
    {
        Http::fake([
            self::WP.'/wp-json/wp/v2/posts*' => Http::response($this->wpPosts(), 200, ['X-WP-TotalPages' => '1']),
            // „Page Links To“: Link-Beiträge leiten auf PDF bzw. Video um.
            self::WP.'/georgsbrief-mai-2025/' => Http::response('', 301, ['Location' => self::WP.'/download/georgsbrief-mai-2025/?wpdmdl=5516&refresh=123']),
            self::WP.'/scoutergy/' => Http::response('', 301, ['Location' => 'https://www.youtube.com/watch?v=51ri-zNfDnE']),
            self::WP.'/wir-leben-demokratie/' => Http::response('<html></html>', 200),
            self::WP.'/download/georgsbrief-mai-2025/*' => Http::response("%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF", 200, ['Content-Disposition' => 'inline;filename="Georgsbrief-Mai-2025.pdf"']),
            self::WP.'/wp-content/uploads/2025/03/info.pdf' => Http::response("%PDF-1.4\n%âãÏÓ\n%%EOF", 200),
            self::WP.'/wp-content/uploads/2025/03/bild.png' => Http::response($this->png(), 200),
            '*' => Http::response('nicht erlaubt', 500),
        ]);
    }

    #[TestDox('Import legt Entwürfe mit bereinigtem Inhalt, Medien und 301-Weiterleitungen an')]
    public function test_import_creates_clean_drafts_with_media_and_redirects(): void
    {
        $this->fakeWordPress();

        $this->artisan('wp:import', ['--since' => '2023-10-01'])->assertSuccessful();

        $this->assertSame(3, Post::query()->whereNotNull('wp_id')->count());
        $post = Post::query()->where('wp_id', 101)->sole();
        $this->assertSame('draft', $post->status);
        $this->assertSame('Wir leben Demokratie!', $post->title);
        $this->assertSame('Auch uns haben Stimmen erreicht', $post->teaser);
        $this->assertSame('2025-03-20', $post->published_at->toDateString());
        $this->assertNull($post->author_id); // ohne Zuordnung → „Redaktion“

        // Bereinigt: keine Block-Klassen, Styles, Skripte, leeren Absätze, Tracking.
        $this->assertStringNotContainsString('wp-block', $post->body);
        $this->assertStringNotContainsString('style=', $post->body);
        $this->assertStringNotContainsString('<script', $post->body);
        $this->assertStringNotContainsString('<iframe', $post->body);
        $this->assertStringNotContainsString('utm_source', $post->body);
        $this->assertStringContainsString('<strong>Stimmen</strong>', $post->body);
        // Interner Link auf importierten Beitrag zeigt auf die neue Adresse.
        $this->assertStringContainsString('href="/aktuelles/georgsbrief-mai-2025"', $post->body);
        // Video-Einbettung wird zum Link.
        $this->assertStringContainsString('href="https://www.youtube.com/watch?v=51ri-zNfDnE"', $post->body);

        // Bild in Originalgröße und PDF in der Mediathek, lokal verlinkt.
        $image = Media::query()->where('source_url', self::WP.'/wp-content/uploads/2025/03/bild.png')->sole();
        $this->assertSame('image/png', $image->mime);
        $this->assertStringStartsWith('/', MediaFetcher::path($image)); // ohne Domain
        $this->assertStringContainsString('src="'.MediaFetcher::path($image).'"', $post->body);
        $this->assertStringContainsString('href="'.MediaFetcher::path(Media::query()->where('source_url', 'like', '%info.pdf')->sole()).'"', $post->body);
        Storage::disk('public')->assertExists($image->path);

        $this->assertContains('Bild ohne Alternativtext – in der Mediathek ergänzen', $post->import_issues);
        // Dateiblock: ein Link, kein doppelter „Herunterladen“-Knopf.
        $this->assertStringContainsString('<p><a href="'.MediaFetcher::path(Media::query()->where('source_url', 'like', '%info.pdf')->sole()).'">Infoblatt_DPSG</a></p>', $post->body);
        $this->assertStringNotContainsString('Herunterladen', $post->body);
        $this->assertFalse($post->import_reviewed);

        // Link-Beiträge: PDF aus dem Download-Manager bzw. YouTube-Link.
        $brief = Post::query()->where('wp_id', 102)->sole();
        $pdf = Media::query()->where('source_url', self::WP.'/download/georgsbrief-mai-2025/?wpdmdl=5516')->sole();
        $this->assertStringContainsString('georgsbrief-mai-2025.pdf', $pdf->path);
        $this->assertStringContainsString('href="'.MediaFetcher::path($pdf).'">PDF öffnen</a>', $brief->body);
        $this->assertStringContainsString('Video auf YouTube ansehen', Post::query()->where('wp_id', 103)->sole()->body);

        $this->assertSame('/aktuelles/wir-leben-demokratie', Redirect::query()->where('from_path', '/wir-leben-demokratie')->value('to_path'));
        $this->assertSame(3, Redirect::query()->where('source', 'wordpress')->count());

        // Nur zugelassene Hosts werden abgerufen (keine fremden Downloads).
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), self::WP));
    }

    #[TestDox('Import ist wiederholbar: keine Duplikate, bearbeitete und veröffentlichte Beiträge bleiben unverändert')]
    public function test_import_is_idempotent_and_respects_local_changes(): void
    {
        $this->fakeWordPress();
        $this->artisan('wp:import', ['--since' => '2023-10-01'])->assertSuccessful();
        $mediaCount = Media::query()->count();

        Post::query()->where('wp_id', 101)->update(['body' => '<p>Im CMS überarbeitet</p>']);
        Post::query()->where('wp_id', 102)->update(['status' => 'published', 'import_reviewed' => true]);

        $this->artisan('wp:import', ['--since' => '2023-10-01'])->assertSuccessful();

        $this->assertSame(3, Post::query()->count());
        $this->assertSame($mediaCount, Media::query()->count());
        $this->assertSame(3, Redirect::query()->count());
        $this->assertSame('<p>Im CMS überarbeitet</p>', Post::query()->where('wp_id', 101)->value('body'));
        $this->assertSame('published', Post::query()->where('wp_id', 102)->value('status'));

        $run = WpImportRun::query()->latest('id')->first();
        $this->assertSame(['neu' => 0, 'aktualisiert' => 1, 'übersprungen' => 2], array_intersect_key($run->counts, ['neu' => 0, 'aktualisiert' => 0, 'übersprungen' => 0]));
    }

    #[TestDox('Testlauf (--dry-run) speichert und lädt nichts, berichtet aber')]
    public function test_dry_run_changes_nothing(): void
    {
        $this->fakeWordPress();

        $this->artisan('wp:import', ['--since' => '2023-10-01', '--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Post::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame(0, Redirect::query()->count());
        $run = WpImportRun::query()->sole();
        $this->assertTrue($run->dry_run);
        $this->assertSame(3, $run->counts['neu']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/wp-content/uploads/'));
    }

    #[TestDox('Alte Adressen leiten per 301 weiter; bestehende Seiten haben Vorrang')]
    public function test_redirects_apply_only_to_unknown_paths(): void
    {
        Redirect::query()->create(['from_path' => '/alter-beitrag/', 'to_path' => '/aktuelles/neuer-beitrag', 'source' => 'wordpress']);
        Redirect::query()->create(['from_path' => '/kontakt', 'to_path' => '/aktuelles/x', 'source' => 'manuell']);
        Page::query()->create(['title' => 'Kontakt', 'slug' => 'kontakt', 'body' => '<p>Hallo</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get('/alter-beitrag')->assertStatus(301)->assertRedirect('/aktuelles/neuer-beitrag');
        $this->get('/Alter-Beitrag/')->assertStatus(301);
        $this->get('/kontakt')->assertOk();
        $this->get('/gibt-es-nicht')->assertNotFound();

        $this->assertSame(2, Redirect::query()->where('from_path', '/alter-beitrag')->value('hits'));
    }

    #[TestDox('Importierte Beiträge lassen sich erst nach Durchsicht veröffentlichen')]
    public function test_imported_post_needs_review_before_publishing(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($user);

        $post = Post::query()->create([
            'title' => 'Alt', 'slug' => 'alt', 'teaser' => 'Kurz', 'body' => '<p>Text</p>', 'status' => 'draft',
            'wp_id' => 5, 'import_issues' => ['Bildrechte prüfen'], 'import_reviewed' => false,
        ]);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertSee('Bildrechte prüfen')
            ->fillForm(['status' => 'published'])
            ->call('save')
            ->assertHasFormErrors(['status']);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['status' => 'published', 'import_reviewed' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('published', $post->fresh()->status);
    }
}
