<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GrantSuperadminMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = (require base_path('database/migrations/2026_10_02_003004_grant_superadmin_to_project_lead.php'));
        $migration->runInTests = true;
        $migration->up();
    }

    private function entraLogin(): void
    {
        Socialite::shouldReceive('driver->user')->andReturn((new SocialiteUser)->map([
            'id' => 'oid-sh', 'name' => 'Sebastian Hafner', 'email' => 'S.Hafner@dpsg-trier.de',
            'user' => ['userPrincipalName' => 'S.Hafner@dpsg-trier.de'],
        ]));
        $this->get('/auth/entra/callback')->assertRedirect('/admin');
    }

    public function test_preprovisioned_account_links_on_first_m365_login(): void
    {
        $this->runMigration();

        $this->entraLogin();
        $user = User::query()->where('entra_oid', 'oid-sh')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->isSuperAdmin());
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/integrations')->assertOk();
    }

    public function test_pending_account_from_earlier_login_is_activated_and_break_glass_freed(): void
    {
        $breakGlass = User::query()->create(['name' => 'Notfall', 'email' => 's.hafner@dpsg-trier.de', 'status' => 'active', 'is_local_admin' => true, 'entra_oid' => 'oid-sh']);

        $this->runMigration();
        $this->runMigration(); // wiederholbar ohne doppelte Rolle

        $this->assertSame('notfall@dpsg-trier.de', $breakGlass->fresh()->email);
        $this->assertNull($breakGlass->fresh()->entra_oid);
        $user = User::query()->where('email', 's.hafner@dpsg-trier.de')->sole();
        $this->assertSame(1, $user->roleAssignments()->count());

        $this->entraLogin();
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertTrue(auth()->user()->isSuperAdmin());
    }
}
