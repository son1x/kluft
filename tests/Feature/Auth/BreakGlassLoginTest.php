<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Deckt den Notfall-Login-Pfad ab (Pflichtenheft 5.2, 13 Abnahmekriterium 2).
 */
class BreakGlassLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(string $password = 'EinSicheresPasswort1234!'): array
    {
        $secret = (new Google2FA)->generateSecretKey();

        $user = User::query()->create([
            'name' => 'Notfall-Admin',
            'email' => 'notfall@dpsg-trier.de',
            'status' => 'active',
            'is_local_admin' => true,
            'password' => Hash::make($password),
            'totp_secret' => $secret,
        ]);

        $user->roleAssignments()->create([
            'role' => 'superadmin',
            'scope_type' => 'global',
        ]);

        return [$user, $secret, $password];
    }

    public function test_login_succeeds_with_correct_password_and_totp(): void
    {
        [$user, $secret, $password] = $this->makeSuperAdmin();
        $otp = (new Google2FA)->getCurrentOtp($secret);

        $response = $this->post('/notfall-login', [
            'email' => $user->email,
            'password' => $password,
            'totp' => $otp,
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas(AuditLog::class, [
            'user_id' => $user->id,
            'action' => 'login.breakglass',
        ]);
    }

    public function test_login_fails_with_wrong_totp(): void
    {
        [$user, , $password] = $this->makeSuperAdmin();

        $response = $this->from('/notfall-login')->post('/notfall-login', [
            'email' => $user->email,
            'password' => $password,
            'totp' => '000000',
        ]);

        $response->assertRedirect('/notfall-login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas(AuditLog::class, [
            'user_id' => $user->id,
            'action' => 'login.breakglass.failed',
        ]);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        [$user, , $password] = $this->makeSuperAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/notfall-login', [
                'email' => $user->email,
                'password' => $password,
                'totp' => '000000',
            ]);
        }

        $otp = (new Google2FA)->getCurrentOtp((new Google2FA)->generateSecretKey());
        $response = $this->post('/notfall-login', [
            'email' => $user->email,
            'password' => $password,
            'totp' => $otp,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_entra_users_cannot_use_the_breakglass_path(): void
    {
        $entraUser = User::query()->create([
            'name' => 'Normale Redakteurin',
            'email' => 'redakteurin@dpsg-trier.de',
            'entra_oid' => 'some-entra-object-id',
            'status' => 'active',
            'is_local_admin' => false,
        ]);

        $response = $this->post('/notfall-login', [
            'email' => $entraUser->email,
            'password' => 'irgendein-passwort',
            'totp' => '123456',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
