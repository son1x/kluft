<?php

namespace Tests\Feature\Users;

use App\Filament\Pages\MeineRollen;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AuditLog;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\RollenAblaufNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Rollen mit Ablaufdatum: gelten bis einschließlich „gültig bis“, Hinweise
 * 30 und 7 Tage vorher, danach automatisch entfernt.
 */
class RollenAblaufTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role, ?string $bis = null): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global', 'gueltig_bis' => $bis]);

        return $user->fresh();
    }

    #[TestDox('Die Rolle gilt bis einschließlich zum Datum, danach nicht mehr')]
    public function test_gueltigkeit(): void
    {
        $this->travelTo('2026-11-10 12:00');
        $kim = $this->user('kim@example.org', 'vorstand', '2026-11-10');
        $this->assertTrue($kim->hasRole('vorstand'));
        $this->assertTrue($kim->hasRoleInScope('vorstand', 'global'));

        $this->travelTo('2026-11-11 00:10');
        $kim = $kim->fresh();
        $this->assertFalse($kim->hasRole('vorstand'));
        $this->assertSame(0, $kim->roleAssignments()->count());
        $this->assertSame(1, RoleAssignment::query()->count(), 'bleibt bis zum Aufräumen stehen');
    }

    #[TestDox('Täglicher Lauf: Hinweise 30 und 7 Tage vorher an Person und Verwaltung, abgelaufene Rollen werden entfernt und protokolliert')]
    public function test_ablauf(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-10 08:15');
        $verwaltung = $this->user('buero@example.org', 'verwaltung');
        $kim = $this->user('kim@example.org', 'vorstand', '2026-12-10');
        $ben = $this->user('ben@example.org', 'kasse', '2026-11-17');
        $this->user('tom@example.org', 'vorstand', '2026-11-20');
        $alt = $this->user('alt@example.org', 'vorstand', '2026-11-09');

        $this->artisan('rollen:ablauf')->expectsOutput('2 Hinweise, 1 Rollen entfernt.')->assertSuccessful();

        Notification::assertSentTo($kim, RollenAblaufNotification::class, fn ($n) => ! $n->verwaltung && $n->eintraege[0]['bis'] === '10.12.2026');
        Notification::assertSentTo($ben, RollenAblaufNotification::class);
        Notification::assertSentTo($verwaltung, RollenAblaufNotification::class, fn ($n) => $n->verwaltung && count($n->eintraege) === 2);
        Notification::assertCount(3);

        $this->assertSame(0, RoleAssignment::query()->where('user_id', $alt->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'role.expired:vorstand|global|')->where('subject_id', $alt->id)->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'role.revoked%')->count());

        $mail = (new RollenAblaufNotification([['person' => 'Kim', 'rolle' => 'Vorstand', 'bis' => '10.12.2026']], true))->toMail($verwaltung);
        $this->assertStringContainsString('Kim: Vorstand – gültig bis 10.12.2026', implode("\n", $mail->introLines));
    }

    #[TestDox('Backend: „gültig bis“ beim Vergeben setzen; „Meine Rollen“ zeigt das Datum')]
    public function test_backend(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = $this->user('admin@example.org', 'admin');
        $kim = $this->user('kim@example.org', 'autor');
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $kim->getRouteKey()])
            ->fillForm(['roleAssignments' => [['rolle' => 'vorstand', 'gueltig_bis' => '2027-03-31']]])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('2027-03-31', $kim->roleAssignments()->sole()->gueltig_bis?->toDateString());

        $this->actingAs($kim->fresh());
        Livewire::test(MeineRollen::class)->assertSee('Gültig bis 31.03.2027');
    }
}
