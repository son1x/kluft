<?php

namespace Tests\Feature\Instanzen;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Zweite Kluft-Instanz: Bezirk Saar-Hochwald (KLUFT_INSTANZ=saar-hochwald).
 * Keine Texte des DV, abgeschaltete Funktionen liefern 404.
 */
#[Group('saar-hochwald')]
class SaarHochwaldTest extends TestCase
{
    use RefreshDatabase;

    /** Typische Texte, die nur zum DV Trier gehören. */
    private const DV_TEXTE = ['Diözesanbüro', 'Diözesanleitung', 'Georgsbrief', 'Georgsbote', 'Bolivien', 'info@dpsg-trier.de', 'Weberbach', 'logo-dv-trier'];

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--force' => true]);
    }

    #[TestDox('Öffentliche Seiten zeigen den Bezirk und keine DV-Texte')]
    public function test_pages_without_dv_texts(): void
    {
        foreach (['/', '/der-bezirk', '/bezirksleitung', '/staemme', '/stufen', '/stufe-woelflinge', '/praevention', '/newsletter',
            '/aktuelles', '/veranstaltungen', '/kalender', '/kontakt', '/impressum', '/datenschutz', '/meine-anmeldungen', '/feed'] as $url) {
            $response = $this->get($url)->assertOk();
            foreach (self::DV_TEXTE as $text) {
                $response->assertDontSee($text, false);
            }
        }

        $this->get('/')->assertSee('DPSG Bezirk Saar-Hochwald')->assertSee('instanz/images/logo-saar-hochwald.png', false)
            ->assertSee('Zur Bezirksleitung')->assertSee('In unseren fünf Stämmen');
        $this->get('/staemme')->assertSee('Unsere Stämme')->assertDontSee('Alle Bezirke');
        $this->get('/newsletter')->assertSee('Newsletter abonnieren');
    }

    #[TestDox('Abgeschaltete Funktionen: keine Routen (404)')]
    public function test_switched_off_features_return_404(): void
    {
        foreach (['/georgsbote', '/georgsbote/2026/1.pdf', '/georgsbrief', '/georgsbrief/danke', '/roadmap', '/bolivien', '/bolivien/aktuelles',
            '/beratung', '/rechtstraeger', '/bezirke-staemme', '/dioezesanbuero', '/dioezesanleitung'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->artisan('wp:import --dry-run')->assertFailed();
    }

    #[TestDox('Alte .html-Adressen der statischen Bezirksseite leiten weiter')]
    public function test_legacy_redirects(): void
    {
        $this->get('/der-bezirk.html')->assertRedirect('/der-bezirk')->assertStatus(301);
        $this->get('/staemme.html')->assertRedirect('/staemme')->assertStatus(301);
    }

    #[TestDox('Backend ohne Georgsbote und WordPress-Import')]
    public function test_backend_without_dv_features(): void
    {
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'superadmin', 'scope_type' => 'global', 'scope_key' => null]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());

        $this->get('/admin')->assertOk()->assertDontSee('Georgsbote')->assertDontSee('WordPress-Import');
        $this->get('/admin/georgsbote')->assertNotFound();
    }
}
