<?php

namespace Tests\Feature\Monitoring;

use App\Filament\Pages\Wartungsmodus;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use App\Support\Wartung;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Wartungsmodus von Hand (Verwaltung → Wartungsmodus, kluft:wartung).
 */
class WartungsmodusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Page::query()->create(['slug' => 'impressum', 'title' => 'Impressum', 'body' => '<p>Impressum</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => $role, 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);

        return $user->fresh();
    }

    #[TestDox('An: Besucher*innen sehen die Wartungsseite (503) mit Text und Ende; Angemeldete die Website mit Band; Anmeldung und Status frei')]
    public function test_besucher_und_angemeldete(): void
    {
        $this->get('/impressum')->assertOk()->assertDontSee('Wartungsmodus ist an');

        Wartung::an('Wir ziehen um.', now()->addHours(2));
        $this->get('/impressum')->assertStatus(503)
            ->assertSee('Wir ziehen um.')->assertSee('Voraussichtlich wieder da')
            ->assertHeader('Retry-After')->assertDontSee('Impressum</p>', false);
        $this->assertNotSame(503, $this->get('/admin/login')->status(), 'Anmeldung bleibt erreichbar');

        $this->actingAs($this->user('autor'));
        $this->get('/impressum')->assertOk()->assertSee('Wartungsmodus ist an.')->assertDontSee('Ausschalten');
        $this->get('/admin')->assertOk()->assertSee('Wartungsmodus ist an.');

        Wartung::aus();
        auth()->logout();
        $this->get('/impressum')->assertOk();
    }

    #[TestDox('Redaktionsstopp: Backend nur für Admins, andere Angemeldete sehen die Wartungsseite')]
    public function test_nur_admins(): void
    {
        Wartung::an(null, null, true);

        $this->actingAs($this->user('autor'));
        $this->get('/admin')->assertStatus(503)->assertSee('Backend ist gerade gesperrt');
        $this->get('/impressum')->assertStatus(503);

        $this->actingAs($this->user('admin'));
        $this->get('/admin')->assertOk()->assertSee('Ausschalten');
    }

    #[TestDox('Backend-Seite: nur Admins; ein- und ausschalten mit Text, Ende und Protokoll')]
    public function test_backend(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->user('verwaltung'));
        $this->get(Wartungsmodus::getUrl())->assertForbidden();

        $this->actingAs($this->user('admin'));
        $this->get(Wartungsmodus::getUrl())->assertOk()->assertSee('Wartungsmodus einschalten');

        Livewire::test(Wartungsmodus::class)
            ->fillForm(['text' => 'Umzug läuft.', 'bis' => now()->addDay()->format('Y-m-d H:i:s'), 'nur_admins' => true])
            ->call('einschalten')
            ->assertNotified('Wartungsmodus ist an');
        $s = Wartung::stand();
        $this->assertSame([true, 'Umzug läuft.', true], [$s['aktiv'], $s['text'], $s['nur_admins']]);
        $this->assertNotNull($s['bis']);
        $this->assertSame('an', Wartungsmodus::getNavigationBadge());

        Livewire::test(Wartungsmodus::class)->call('ausschalten')->assertNotified('Wartungsmodus ist aus');
        $this->assertFalse(Wartung::aktiv());
        $this->assertSame('Umzug läuft.', Wartung::stand()['text'], 'Text bleibt für das nächste Mal');
        $this->assertSame(2, AuditLog::query()->whereIn('action', ['wartung.an', 'wartung.aus'])->count());
    }

    #[TestDox('Befehl kluft:wartung an/aus/status (auch über Actions → Server-Prüfung)')]
    public function test_befehl(): void
    {
        $this->artisan('kluft:wartung')->expectsOutputToContain('Wartungsmodus: aus')->assertSuccessful();
        $this->artisan('kluft:wartung', ['aktion' => 'an', '--text' => 'Server-Umzug'])->expectsOutputToContain('Text: Server-Umzug')->assertSuccessful();
        $this->assertTrue(Wartung::aktiv());
        $this->get('/impressum')->assertStatus(503)->assertSee('Server-Umzug');
        $this->artisan('kluft:wartung', ['aktion' => 'aus'])->expectsOutputToContain('Wartungsmodus: aus')->assertSuccessful();
        $this->artisan('kluft:wartung', ['aktion' => 'quatsch'])->assertFailed();
    }
}
