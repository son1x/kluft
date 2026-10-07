<?php

namespace Tests\Feature\Instanzen;

use App\Models\User;
use App\Support\KluftVersion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Redaktionsbereich: unten Kluft-Logo, Name und Git-Revision als Versionstag.
 */
class KluftVersionTest extends TestCase
{
    use RefreshDatabase;

    private const REVISION = '28a37ee0123456789abcdef0123456789abcdef0';

    #[TestDox('Backend zeigt unten Kluft, die kurze Revision mit Link zum Commit und ein Info-Popup mit Namenserklärung und Copyright')]
    public function test_backend_footer(): void
    {
        config(['app.revision' => self::REVISION, 'app.repository' => 'https://github.com/son1x/kluft']);
        $user = User::query()->create(['name' => 'Test', 'email' => 'redaktion@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global', 'scope_key' => null]);
        Filament::setCurrentPanel('admin');

        $this->actingAs($user->fresh())->get('/admin')
            ->assertOk()
            ->assertSee('class="kluft-fuss"', false)
            ->assertSee('<span class="name">Kluft</span>', false)
            ->assertSee('popovertarget="kluft-info"', false)
            ->assertSee('Die Kluft tragen alle gleich')
            ->assertSee('© 2026', false)
            ->assertSee(config('app.copyright'))
            ->assertSee('>28a37ee</a>', false)
            ->assertSee('https://github.com/son1x/kluft/commit/'.self::REVISION, false);
    }

    #[TestDox('Notfall-Login zeigt den Versionstag ebenfalls')]
    public function test_notfall_login_footer(): void
    {
        config(['app.revision' => self::REVISION]);

        $this->get(route('notfall-login'))->assertOk()->assertSee('>28a37ee</a>', false);
    }

    #[TestDox('Ungültige Revision wird nicht ausgegeben; ohne Wert wird .git gelesen')]
    public function test_revision_validation(): void
    {
        config(['app.revision' => '<script>']);
        $this->assertNotSame('<script>', KluftVersion::revision());

        config(['app.revision' => self::REVISION, 'app.repository' => '']);
        $this->assertSame('28a37ee', KluftVersion::kurz());
        $this->assertNull(KluftVersion::link());
    }
}
