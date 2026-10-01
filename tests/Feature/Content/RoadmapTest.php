<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Support\Roadmap;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /roadmap: Stand je Pflichtenheft-Abschnitt und offene Punkte.
 */
class RoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_roadmap_covers_every_heading_of_the_pflichtenheft(): void
    {
        preg_match_all('/^#{2,3} (\d+(?:\.\d+)?)\.? (.+)$/m', file_get_contents(base_path('docs/pflichtenheft.md')), $m, PREG_SET_ORDER);
        $sections = app(Roadmap::class)->data()['sections'];

        $this->assertNotEmpty($m);
        foreach ($m as [, $num, $title]) {
            $this->assertArrayHasKey($num, $sections, "Abschnitt {$num} fehlt in resources/data/roadmap.php");
            $this->assertSame(trim($title), $sections[$num]['title'], "Titel von Abschnitt {$num} weicht ab");
            $this->assertArrayHasKey($sections[$num]['status'], Roadmap::STATUSES);
        }
        $this->assertCount(count($m), $sections, 'roadmap.php enthält Abschnitte, die es im Pflichtenheft nicht mehr gibt');
    }

    public function test_page_shows_sections_and_auto_checked_todos(): void
    {
        $this->get('/roadmap')->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('6.7 Veranstaltungsmanagement (Eigenentwicklung) – Phase 1')
            ->assertSee('Deine offenen Punkte')
            ->assertSee('Cronjob im Hosting-Panel anlegen')
            ->assertSee('Status: Erledigt', false);

        $this->assertFalse(collect(app(Roadmap::class)->todos())->firstWhere('key', 'notfall-login')['done']);
        $this->assertFalse(collect(app(Roadmap::class)->todos())->firstWhere('key', 'cron')['done']);

        User::query()->create(['name' => 'Notfall', 'email' => 'notfall@example.org', 'status' => 'active', 'is_local_admin' => true, 'totp_secret' => 'ABCDEFGHIJKLMNOP']);
        Cache::forever(Roadmap::HEARTBEAT_KEY, now()->timestamp);

        $todos = collect(app(Roadmap::class)->todos());
        $this->assertTrue($todos->firstWhere('key', 'notfall-login')['done']);
        $this->assertTrue($todos->firstWhere('key', 'cron')['done']);
        $this->assertNull($todos->firstWhere('key', 'domain-bolivien')['done']);

        // Unlesbares TOTP-Secret (fremder APP_KEY) gilt nicht als erledigt.
        DB::table('users')->where('email', 'notfall@example.org')
            ->update(['totp_secret' => (new Encrypter(random_bytes(32), 'aes-256-cbc'))->encryptString('X')]);
        $this->assertFalse(collect(app(Roadmap::class)->todos())->firstWhere('key', 'notfall-login')['done']);
    }
}
