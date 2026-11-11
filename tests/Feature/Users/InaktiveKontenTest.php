<?php

namespace Tests\Feature\Users;

use App\Mail\KontoInaktivMail;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Ungenutzte Backend-Konten: Vorwarnung nach 11, Sperre nach 12 Monaten
 * ohne Anmeldung; Notfall-Admins ausgenommen.
 */
class InaktiveKontenTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, ?string $login, bool $lokal = false): User
    {
        $user = User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.org', 'status' => 'active']);
        $user->forceFill(['last_login_at' => $login ? now()->sub($login) : null, 'is_local_admin' => $lokal, 'created_at' => now()->subYears(2)])->save();

        return $user->fresh();
    }

    #[TestDox('Vorwarnung, dann Sperre frühestens 14 Tage später; Anmeldung setzt zurück; Notfall-Admin bleibt')]
    public function test_ablauf(): void
    {
        Mail::fake();
        $aktiv = $this->user('Aktiv', '2 months');
        $alt = $this->user('Alt', '13 months');
        $bald = $this->user('Bald', '11 months 2 days');
        $notfall = $this->user('Notfall', '3 years', lokal: true);

        $this->artisan('konten:inaktive')->assertSuccessful();
        Mail::assertQueued(KontoInaktivMail::class, 2);
        $this->assertNotNull($alt->fresh()->inaktiv_gewarnt_at);
        $this->assertNotNull($bald->fresh()->inaktiv_gewarnt_at);
        $this->assertNull($aktiv->fresh()->inaktiv_gewarnt_at);
        $this->assertSame('active', $alt->fresh()->status, 'Erst warnen, dann sperren');

        // Bald meldet sich an: Warnung zurückgesetzt.
        $bald->fresh()->forceFill(['last_login_at' => now()])->save();
        $this->assertNull($bald->fresh()->inaktiv_gewarnt_at);

        $this->travel(15)->days();
        $this->artisan('konten:inaktive')->assertSuccessful();
        $this->assertSame('disabled', $alt->fresh()->status);
        $this->assertSame('active', $bald->fresh()->status);
        $this->assertSame('active', $notfall->fresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'konto.inaktiv_gesperrt')->count());
        Mail::assertQueued(KontoInaktivMail::class, fn (KontoInaktivMail $m) => $m->gesperrt && $m->name === 'Alt');
    }

    #[TestDox('Mit 0 Monaten ausgeschaltet')]
    public function test_aus(): void
    {
        config(['dpsg.konten_sperren_monate' => 0]);
        $alt = $this->user('Alt', '3 years');
        $this->artisan('konten:inaktive')->assertSuccessful();
        $this->assertNull($alt->fresh()->inaktiv_gewarnt_at);
    }
}
