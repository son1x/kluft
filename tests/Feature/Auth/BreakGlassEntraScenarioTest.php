<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\TestDox;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Ablauf aus der Praxis: Notfall-Login → Klick auf „Benutzer“ → Sitzung
 * weg → Microsoft-Anmeldung → erneuter Notfall-Login.
 */
class BreakGlassEntraScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function breakGlass(string $email): array
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = User::query()->create([
            'name' => 'Notfall', 'email' => $email, 'status' => 'active', 'is_local_admin' => true,
            'password' => Hash::make('EinSicheresPasswort1234!'), 'totp_secret' => $secret,
        ]);
        $user->roleAssignments()->create(['role' => 'superadmin', 'scope_type' => 'global']);

        return [$user, $secret];
    }

    private function entraLogin(string $email): TestResponse
    {
        Socialite::shouldReceive('driver->user')->andReturn((new SocialiteUser)->map([
            'id' => 'oid-1', 'name' => 'Sebastian', 'email' => $email, 'user' => ['userPrincipalName' => $email],
        ]));

        return $this->get('/auth/entra/callback');
    }

    private function notfall(string $email, string $secret): TestResponse
    {
        return $this->post('/notfall-login', [
            'email' => $email, 'password' => 'EinSicheresPasswort1234!',
            'totp' => (new Google2FA)->getCurrentOtp($secret),
        ]);
    }

    #[TestDox('Notfall-Account und M365-Konto mit unterschiedlicher Adresse bleiben getrennt')]
    public function test_different_m365_address(): void
    {
        [$admin, $secret] = $this->breakGlass('notfall@dpsg-trier.de');
        $this->notfall('notfall@dpsg-trier.de', $secret)->assertRedirect('/admin');
        $this->get('/admin/users')->assertOk();

        $this->post('/logout');
        auth()->logout();
        $this->get('/admin/users')->assertRedirect();
        $this->entraLogin('sebastian@dpsg-trier.de')->assertRedirect();
        $this->followingRedirects()->get('/admin/users')->assertStatus(403);

        $response = $this->notfall('notfall@dpsg-trier.de', $secret);
        $response->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk();
    }

    #[TestDox('Eine M365-Anmeldung wird nie mit dem Notfall-Account verknüpft')]
    public function test_m365_login_never_links_to_the_break_glass_account(): void
    {
        [$admin, $secret] = $this->breakGlass('sebastian@dpsg-trier.de');

        $this->entraLogin('Sebastian@dpsg-trier.de')->assertStatus(409)->assertSee('dem Notfall-Account vorbehalten');
        $this->assertGuest();
        $this->assertNull($admin->fresh()->entra_oid);
        $this->assertSame(1, User::query()->count());

        // Notfall-Login funktioniert weiter, auch mit anderer Groß-/Kleinschreibung.
        $this->notfall('Sebastian@DPSG-Trier.de', $secret)->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    #[TestDox('Neu-Einrichten des Notfall-Accounts vergibt eigene Adresse und neues TOTP-Geheimnis')]
    public function test_reset_command_gives_break_glass_its_own_address_and_new_secret(): void
    {
        [$admin] = $this->breakGlass('sebastian@dpsg-trier.de');
        // Verknüpft und mit unlesbarem TOTP-Secret – der schlimmste Fall.
        DB::table('users')->where('id', $admin->id)->update([
            'entra_oid' => 'oid-1',
            'totp_secret' => (new Encrypter(random_bytes(32), 'aes-256-cbc'))->encryptString('ALT'),
        ]);

        $this->artisan('cms:create-superadmin')->assertFailed();
        $this->artisan('cms:create-superadmin --reset')
            ->expectsQuestion('E-Mail-Adresse (Enter = unverändert)', 'notfall@dpsg-trier.de')
            ->expectsQuestion('Passwort (mind. 16 Zeichen)', 'NeuesSicheresPasswort99')
            ->expectsQuestion('Passwort wiederholen', 'NeuesSicheresPasswort99')
            ->assertSuccessful();

        $admin->refresh();
        $this->assertSame('notfall@dpsg-trier.de', $admin->email);
        $this->assertNull($admin->entra_oid);
        $this->assertTrue(Hash::check('NeuesSicheresPasswort99', $admin->password));
        $this->assertSame(1, $admin->roleAssignments()->where('role', 'superadmin')->count());
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{16,}$/', $admin->totp_secret);
    }

    #[TestDox('Unlesbares TOTP-Geheimnis führt zu klarer Meldung statt Fehler 500')]
    public function test_unreadable_totp_secret_gives_clear_message_instead_of_500(): void
    {
        [$admin, $secret] = $this->breakGlass('notfall@dpsg-trier.de');
        // Mit fremdem Schlüssel verschlüsselt, wie während eines Deploys passiert.
        DB::table('users')->where('id', $admin->id)
            ->update(['totp_secret' => (new Encrypter(random_bytes(32), 'aes-256-cbc'))->encryptString($secret)]);

        $this->notfall('notfall@dpsg-trier.de', $secret)
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => 'Der Notfall-Account muss neu eingerichtet werden (TOTP-Geheimnis nicht lesbar). Bitte per SSH „php artisan cms:create-superadmin“ ausführen.']);
        $this->assertGuest();
    }
}
