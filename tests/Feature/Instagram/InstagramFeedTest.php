<?php

namespace Tests\Feature\Instagram;

use App\Filament\Resources\InstagramKonten\Pages\CreateInstagramKonto;
use App\Filament\Resources\InstagramKonten\Pages\ListInstagramKonten;
use App\Models\InstagramBeitrag;
use App\Models\InstagramKonto;
use App\Models\Page;
use App\Models\User;
use App\Services\Instagram\InstagramFeed;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Instagram-Einblendung (docs/instagram.md): neueste Beiträge eigener
 * Konten, Bilder lokal, Block oberhalb von „Aktuelles“.
 */
class InstagramFeedTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $media = [];

    private int $bildAbrufe = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Filament::setCurrentPanel('admin');
        $this->media = [
            ['id' => '1801', 'caption' => "Sommerlager 2026\n#dpsg", 'media_type' => 'IMAGE', 'media_url' => 'https://scontent-fra5-1.cdninstagram.com/a.jpg', 'permalink' => 'https://www.instagram.com/p/AAA/', 'timestamp' => '2026-10-20T10:00:00+0000'],
            ['id' => '1802', 'media_type' => 'VIDEO', 'media_url' => 'https://video.cdninstagram.com/v.mp4', 'thumbnail_url' => 'https://scontent-fra5-1.cdninstagram.com/t.jpg', 'permalink' => 'https://www.instagram.com/reel/BBB/', 'timestamp' => '2026-10-19T10:00:00+0000'],
            ['id' => '1803', 'caption' => 'Fremdes Bild', 'media_type' => 'IMAGE', 'media_url' => 'https://boese.example.org/x.jpg', 'permalink' => 'https://www.instagram.com/p/CCC/', 'timestamp' => '2026-10-18T10:00:00+0000'],
        ];
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_starts_with($url, 'https://graph.instagram.com/v23.0/me/media')) {
                return str_contains($url, 'access_token=gueltig')
                    ? Http::response(['data' => $this->media])
                    : Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400);
            }
            if (str_starts_with($url, 'https://graph.instagram.com/v23.0/me')) {
                return str_contains($url, 'access_token=gueltig')
                    ? Http::response(['user_id' => '17841', 'username' => 'dpsgtrier'])
                    : Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400);
            }
            if (str_contains($url, 'cdninstagram.com')) {
                $this->bildAbrufe++;

                return Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response('', 404);
        });
    }

    private function jpeg(): string
    {
        $bild = imagecreatetruecolor(1080, 1350);
        ob_start();
        imagejpeg($bild);

        return (string) ob_get_clean();
    }

    private function konto(array $attrs = []): InstagramKonto
    {
        return InstagramKonto::query()->create([
            'name' => 'DPSG Trier', 'benutzername' => 'dpsgtrier', 'access_token' => 'gueltig', 'orte' => ['startseite'],
            'anzahl' => 8, 'token_ablauf' => now()->addDays(50), 'token_erneuert' => now()->subDays(5), ...$attrs,
        ]);
    }

    #[TestDox('Abruf: Bilder lokal verkleinert (Video als Vorschaubild), fremde Bildserver abgewiesen, ältere Beiträge samt Bild entfernt')]
    public function test_abruf(): void
    {
        $konto = $this->konto();
        $this->assertSame(2, app(InstagramFeed::class)->abrufen($konto));

        $beitraege = $konto->beitraege()->orderBy('media_id')->get();
        $this->assertSame(['1801', '1802'], $beitraege->pluck('media_id')->all());
        $this->assertSame('VIDEO', $beitraege[1]->art);
        Storage::disk('public')->assertExists($beitraege[0]->bild);
        $this->assertSame(640, getimagesizefromstring(Storage::disk('public')->get($beitraege[0]->bild))[0]);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'boese.example.org'));

        // Zweiter Abruf: vorhandene Bilder nicht erneut laden; verschwundener Beitrag samt Datei weg.
        $alt = $beitraege[1]->bild;
        $this->bildAbrufe = 0;
        array_splice($this->media, 1, 1);
        app(InstagramFeed::class)->abrufen($konto);
        $this->assertSame(0, $this->bildAbrufe);
        $this->assertSame(['1801'], $konto->beitraege()->pluck('media_id')->all());
        Storage::disk('public')->assertMissing($alt);
        $this->assertNull($konto->fresh()->fehler);
    }

    #[TestDox('Startseite: Block „Auf Instagram“ oberhalb von „Aktuelles“, lokale Bilder, Link zu Instagram; ohne Zuordnung nichts')]
    public function test_startseite(): void
    {
        $konto = $this->konto();
        app(InstagramFeed::class)->abrufen($konto);
        $bild = InstagramBeitrag::query()->where('media_id', '1801')->value('bild');

        Page::query()->create(['slug' => 'start', 'title' => 'Start', 'body' => '<p>Willkommen</p>', 'status' => 'published', 'published_at' => now()]);
        $html = (string) $this->get('/')->getContent();
        if (str_contains($html, 'aktuelles-heading')) {
            $this->assertLessThan(strpos($html, 'aktuelles-heading'), strpos($html, 'Auf Instagram'));
        }

        $this->get('/')->assertOk()
            ->assertSee('Auf Instagram')
            ->assertSee(Storage::disk('public')->url($bild), false)
            ->assertSee('href="https://www.instagram.com/p/AAA/"', false)
            ->assertSee('@dpsgtrier auf Instagram folgen')
            ->assertDontSee('cdninstagram.com', false);

        // Fachbereich (nur Instanzen mit Sektionen, z. B. DV Trier: Bolivien).
        if (config('dpsg.sections.bolivien')) {
            $konto->update(['orte' => ['section:bolivien']]);
            Page::query()->create(['slug' => config('dpsg.sections.bolivien.landing'), 'title' => 'Bolivien', 'body' => '<p>Partnerschaft</p>', 'status' => 'published', 'published_at' => now(), 'section' => 'bolivien']);
            $this->get('/'.config('dpsg.sections.bolivien.landing'))->assertOk()->assertSeeInOrder(['Auf Instagram', 'sektion-aktuelles'], false);
            $this->get('/')->assertOk()->assertDontSee('Auf Instagram');
        }

        $konto->update(['orte' => []]);
        $this->get('/')->assertOk()->assertDontSee('Auf Instagram');
    }

    #[TestDox('Fehler beim Abruf landen am Konto; Backend nur für Admins, Token wird beim Anlegen geprüft')]
    public function test_backend(): void
    {
        $konto = $this->konto(['access_token' => 'abgelaufen']);
        app(InstagramFeed::class)->abrufen($konto);
        $this->assertStringContainsString('Invalid OAuth', (string) $konto->fresh()->fehler);

        $redaktion = User::query()->create(['name' => 'r', 'email' => 'r@example.org', 'status' => 'active']);
        $redaktion->roleAssignments()->create(['role' => 'redaktion', 'scope_type' => 'global', 'scope_key' => null]);
        $this->actingAs($redaktion)->get(ListInstagramKonten::getUrl())->assertForbidden();

        $admin = User::query()->create(['name' => 'a', 'email' => 'a@example.org', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global', 'scope_key' => null]);
        $this->actingAs($admin)->get(ListInstagramKonten::getUrl())->assertOk()->assertSee('DPSG Trier')->assertSee('Anleitung: Token erzeugen');
        Livewire::actingAs($admin)->test(ListInstagramKonten::class)->mountAction('anleitung')->assertActionMounted('anleitung');
        $anleitung = view('filament.instagram-anleitung')->render();
        $this->assertStringContainsString('API-Einrichtung mit Instagram-Login', $anleitung);
        $this->assertStringContainsString('https://developers.facebook.com/apps/', $anleitung);

        Livewire::actingAs($admin)->test(CreateInstagramKonto::class)
            ->fillForm(['name' => 'Falsch', 'access_token' => 'falsch', 'anzahl' => 6, 'orte' => ['startseite']])
            ->call('create');
        $this->assertFalse(InstagramKonto::query()->where('name', 'Falsch')->exists());

        Livewire::actingAs($admin)->test(CreateInstagramKonto::class)
            ->fillForm(['name' => 'Neu', 'access_token' => 'gueltig', 'anzahl' => 6, 'orte' => ['startseite']])
            ->call('create')->assertHasNoFormErrors();
        $neu = InstagramKonto::query()->where('name', 'Neu')->sole();
        $this->assertSame(['dpsgtrier', 'gueltig'], [$neu->benutzername, $neu->access_token]);
        $this->assertSame(2, $neu->beitraege()->count());
        $this->assertStringNotContainsString('gueltig', (string) DB::table('instagram_konten')->where('id', $neu->id)->value('access_token'));
    }
}
