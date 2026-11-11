<?php

namespace Tests\Feature\Users;

use App\Filament\Pages\RechtePruefungPage;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\RechtePruefung;
use App\Support\WasStehtAn;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Jährliche Rechte-Prüfung: Wer hat sensible Rechte? (docs/datenschutz-ueberblick.md)
 */
class RechtePruefungTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function user(string $name, string $role, string $scope = 'global', ?string $key = null, ?string $bis = null): User
    {
        $user = User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scope, 'scope_key' => $key, 'gueltig_bis' => $bis]);

        return $user->fresh();
    }

    #[TestDox('Liste: nur Personen mit sensiblen Rechten, mit Hinweisen; Abschluss wird gespeichert und protokolliert')]
    public function test_pruefung(): void
    {
        $admin = $this->user('Admin', 'admin');
        $admin->forceFill(['last_login_at' => now()])->save();
        $this->user('Kasse', 'kasse', bis: now()->addYear()->toDateString());
        $this->user('Autorin', 'autor', 'own');
        $this->user('Vorstand', 'vorstand');

        $namen = RechtePruefung::personen()->map(fn (array $p) => $p['user']->name)->all();
        $this->assertSame(['Admin', 'Kasse', 'Vorstand'], $namen);
        $vorstand = RechtePruefung::personen()->firstWhere(fn (array $p) => $p['user']->name === 'Vorstand');
        $this->assertSame(['Prävention'], $vorstand['rechte']);
        $this->assertContains('noch nie angemeldet', $vorstand['hinweise']);
        $this->assertContains('ohne „gültig bis“', $vorstand['hinweise']);

        $this->actingAs($admin);
        $this->assertTrue(RechtePruefung::faellig());
        $this->assertContains('Rechte-Prüfung fällig', array_column(WasStehtAn::fuer($admin), 'titel'));

        Livewire::test(RechtePruefungPage::class)->assertOk()
            ->assertSee('3 Personen mit sensiblen Rechten')->assertSee('Noch nie geprüft')
            ->callAction('abschliessen');

        $this->assertFalse(RechtePruefung::faellig());
        $this->assertSame('Admin', RechtePruefung::letzte()['von']);
        $this->assertSame(1, AuditLog::query()->where('action', 'rechte.geprueft:3')->count());
        $this->assertNotContains('Rechte-Prüfung fällig', array_column(WasStehtAn::fuer($admin), 'titel'));

        $this->travel(13)->months();
        $this->assertTrue(RechtePruefung::faellig());
    }

    #[TestDox('Zugriff nur mit „Benutzer und alle Rollen“; Bereichsrollen ohne Ablauf erscheinen in „Was steht an“')]
    public function test_zugriff_und_hinweis(): void
    {
        $leitung = $this->user('Leitung', 'stufenadmin', 'stage', 'woelflinge');
        $this->actingAs($leitung);
        $this->get(RechtePruefungPage::getUrl())->assertForbidden();

        $buero = $this->user('Buero', 'verwaltung');
        $this->actingAs($buero);
        $this->get(RechtePruefungPage::getUrl())->assertOk();
        $this->assertContains('1 Bereichsrolle ohne „gültig bis“', array_column(WasStehtAn::fuer($buero), 'titel'));
    }
}
