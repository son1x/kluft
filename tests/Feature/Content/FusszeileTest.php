<?php

namespace Tests\Feature\Content;

use App\Filament\Pages\Fusszeile;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Verwaltung → Fußzeile: Kontakt, Service-Links, „DPSG im Netz“ und die
 * dritte Spalte anpassen; ohne Anpassung gilt der Standard der Instanz.
 */
class FusszeileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Page::query()->firstOrCreate(['slug' => 'impressum'], ['title' => 'Impressum', 'body' => '<p>Impressum</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    private function user(string $role, string $scopeType = 'global'): User
    {
        $user = User::query()->create(['name' => ucfirst($role), 'email' => "{$role}@example.org", 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType]);

        return $user->fresh();
    }

    #[TestDox('Nur Verwaltung und Admins öffnen die Seite')]
    public function test_zugriff(): void
    {
        $this->actingAs($this->user('autor', 'own'))->get(Fusszeile::getUrl())->assertForbidden();
        $this->actingAs($this->user('verwaltung'))->get(Fusszeile::getUrl())->assertOk()->assertSee('Fußzeile der Website');
    }

    #[TestDox('Anpassen: Kontakt, eigener Service-Link, eigene Liste „DPSG im Netz“, dritte Spalte leer; mit Audit-Eintrag; Zurücksetzen stellt den Standard her')]
    public function test_anpassen_und_zuruecksetzen(): void
    {
        $standardName = (string) config('dpsg.contact.name');
        $this->actingAs($this->user('verwaltung'));

        Livewire::test(Fusszeile::class)
            ->fillForm([
                'kontakt' => ['office' => 'Geschäftsstelle', 'phone' => '0681 123 45'],
                'service' => [['label' => 'Satzung', 'url' => '/satzung']],
                'netz_eigen' => true,
                'netz' => [['label' => 'Bistum', 'url' => 'https://www.bistum-trier.de']],
                'spalte3' => 'leer',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Gespeichert');

        $this->get('/impressum')->assertOk()
            ->assertSee($standardName)                       // nicht überschrieben: Standard
            ->assertSee('Geschäftsstelle')
            ->assertSee('tel:+4968112345', false)
            ->assertSee('Satzung')->assertSee(url('/satzung'), false)
            ->assertSee('https://www.bistum-trier.de', false)
            ->assertDontSee('dpsg.de – Bundesebene')
            ->assertDontSee('images/footer/zelte', false)
            ->assertDontSee('abonnieren →');
        $this->assertSame(1, AuditLog::query()->where('action', 'fusszeile.gespeichert')->count());

        Livewire::test(Fusszeile::class)->callAction('zuruecksetzen')->assertNotified('Standard wiederhergestellt');
        $antwort = $this->get('/impressum')->assertOk()->assertSee('dpsg.de – Bundesebene')->assertDontSee('Geschäftsstelle');
        Features::newsletter()
            ? $antwort->assertSee(route('newsletter.index'), false)
            : $antwort->assertSee('images/footer/zelte.webp', false);
    }

    #[TestDox('Eigenes Bild in der dritten Spalte, optional verlinkt; ungültige Adressen werden abgelehnt')]
    public function test_bild_und_pruefung(): void
    {
        $bild = Media::query()->create(['path' => 'media/wappen.png', 'mime' => 'image/png', 'size' => 100, 'alt_text' => 'Stammeswappen', 'credit' => 'Stamm']);
        $this->actingAs($this->user('verwaltung'));

        Livewire::test(Fusszeile::class)
            ->fillForm(['service' => [['label' => 'Böse', 'url' => 'javascript:alert(1)']]])
            ->call('save')
            ->assertHasFormErrors(['service.0.url']);

        Livewire::test(Fusszeile::class)
            ->fillForm(['spalte3' => 'bild', 'bild_media_id' => $bild->id, 'bild_link' => 'https://example.org/stamm'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/impressum')->assertOk()
            ->assertSee('alt="Stammeswappen"', false)
            ->assertSee('https://example.org/stamm', false)
            ->assertDontSee('images/footer/zelte', false);
    }
}
