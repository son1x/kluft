<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\User;
use App\Support\Features;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Vor Ort, Stufe 3 und 4: installierbare App, Service Worker, Daten für den Gerätespeicher.
 */
class VorOrtAppTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Manifest, Service Worker und Symbol sind ohne Anmeldung abrufbar, nur mit eingeschalteter Funktion')]
    public function test_app_bausteine(): void
    {
        $this->get('/vor-ort/manifest.webmanifest')->assertNotFound();
        $this->get('/vor-ort/sw.js')->assertNotFound();
        $this->get('/vor-ort/symbol/192.png')->assertNotFound();

        Features::setzen(Features::VOR_ORT, true);

        $manifest = $this->get('/vor-ort/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
        $m = $manifest->json();
        $this->assertSame(['/vor-ort', '/vor-ort/', 'standalone'], [$m['start_url'], $m['scope'], $m['display']]);
        $this->assertSame(['/vor-ort/symbol/192.png', '/vor-ort/symbol/512.png'], array_column($m['icons'], 'src'));

        $sw = $this->get('/vor-ort/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/vor-ort/');
        $this->assertStringContainsString('text/javascript', $sw->headers->get('Content-Type'));
        $this->assertStringContainsString("pathname.startsWith('/vor-ort/api/')", $sw->getContent(), 'Daten dürfen nie zwischengespeichert werden');

        foreach (['192' => 192, '512' => 512] as $name => $groesse) {
            $bild = $this->get("/vor-ort/symbol/$name.png")->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
            $this->assertSame([$groesse, $groesse], array_slice(getimagesizefromstring($bild), 0, 2));
        }
        $this->get('/vor-ort/symbol/999.png')->assertNotFound();
    }

    #[TestDox('Die Seite verweist auf Manifest und Symbol; der Service Worker liegt im Geltungsbereich /vor-ort/')]
    public function test_seite_verweist_auf_app(): void
    {
        Features::setzen(Features::VOR_ORT, true);
        $stufe = (string) array_key_first(array_diff_key(Event::stufen(), [Event::LEITENDE => true]));
        $u = User::query()->create(['name' => 'Team', 'email' => 'team@example.org', 'status' => 'active']);
        $u->roleAssignments()->create(['role' => RoleCatalog::leitungLiestVeranstaltungen() ? 'veranstaltungen' : 'stufenadmin', 'scope_type' => 'stage', 'scope_key' => $stufe]);

        $this->actingAs($u->fresh())->get('/vor-ort')->assertOk()
            ->assertSee('rel="manifest" href="/vor-ort/manifest.webmanifest"', false)
            ->assertSee('rel="apple-touch-icon" href="/vor-ort/symbol/180.png"', false)
            ->assertSee('Sperren');
    }

    #[TestDox('Daten fürs Gerät: Stand und Löschzeitpunkt (7 Tage nach Ende der Veranstaltung)')]
    public function test_stand_und_loeschfrist(): void
    {
        Features::setzen(Features::VOR_ORT, true);
        $stufe = (string) array_key_first(array_diff_key(Event::stufen(), [Event::LEITENDE => true]));
        $event = Event::query()->create(['title' => 'Lager', 'slug' => 'lager', 'starts_at' => now()->startOfDay()->addHours(10),
            'ends_at' => now()->startOfDay()->addDays(2)->addHours(14), 'status' => 'open', 'stage' => $stufe]);
        $u = User::query()->create(['name' => 'Team', 'email' => 'team@example.org', 'status' => 'active']);
        $u->roleAssignments()->create(['role' => RoleCatalog::leitungLiestVeranstaltungen() ? 'veranstaltungen' : 'stufenadmin', 'scope_type' => 'stage', 'scope_key' => $stufe]);

        $d = $this->actingAs($u->fresh())->getJson('/vor-ort/api/veranstaltungen/'.$event->getKey())->assertOk()->json();

        $this->assertEqualsWithDelta(now()->timestamp, strtotime($d['stand']), 5);
        $this->assertSame(now()->startOfDay()->addDays(2)->endOfDay()->addDays(7)->toDateString(), substr($d['loeschen_am'], 0, 10));
        // Die API selbst setzt keine Zwischenspeicher-Köpfe für gemeinsame Caches.
        $this->assertStringNotContainsString('public', (string) $this->actingAs($u->fresh())->getJson('/vor-ort/api/veranstaltungen/'.$event->getKey())->headers->get('Cache-Control'));
    }
}
