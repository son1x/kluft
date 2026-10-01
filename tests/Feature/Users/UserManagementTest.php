<?php

namespace Tests\Feature\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Benutzerverwaltung, Pflichtenheft 5.4–5.6 (F-USR-01/03/05/06/07) und
 * Abnahmekriterium 16.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function makeUser(string $email, array $assignments = [], string $status = 'active', bool $localAdmin = false): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => $status, 'is_local_admin' => $localAdmin]);
        foreach ($assignments as [$role, $scopeType, $scopeKey]) {
            $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);
        }

        return $user->fresh();
    }

    public function test_verwaltung_preprovisions_account_with_role(): void
    {
        $verwaltung = $this->makeUser('buero@dpsg-trier.de', [['verwaltung', 'global', null]]);
        $this->actingAs($verwaltung);
        $undo = Repeater::fake();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Neue Referentin',
                'email' => 'Neue.Referentin@DPSG-Trier.de',
                'status' => 'active',
                'roleAssignments' => [['assignment' => 'stufenadmin|stage|woelflinge']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();

        $created = User::query()->where('email', 'neue.referentin@dpsg-trier.de')->firstOrFail();
        $this->assertSame('neue.referentin@dpsg-trier.de', $created->upn);
        $this->assertTrue($created->hasRoleInScope('stufenadmin', 'stage', 'woelflinge'));
        $this->assertTrue(AuditLog::query()->where('action', 'role.granted:stufenadmin|stage|woelflinge')->where('user_id', $verwaltung->id)->exists());
    }

    public function test_stufenadmin_can_unlock_pending_user_as_autor_in_own_stage(): void
    {
        $stufenadmin = $this->makeUser('biber-admin@dpsg-trier.de', [['stufenadmin', 'stage', 'biber']]);
        $pending = $this->makeUser('neu@dpsg-trier.de', status: 'pending');
        $this->actingAs($stufenadmin);
        $undo = Repeater::fake();

        Livewire::test(EditUser::class, ['record' => $pending->getRouteKey()])
            ->fillForm(['roleAssignments' => [['assignment' => 'autor|own|biber']]])
            ->call('save')
            ->assertHasNoFormErrors();
        $undo();

        $pending->refresh();
        $this->assertSame('active', $pending->status);
        $this->assertTrue($pending->hasRoleInScope('autor', 'own', 'biber'));
    }

    public function test_stufenadmin_edit_keeps_assignments_outside_own_scope(): void
    {
        $stufenadmin = $this->makeUser('biber-admin@dpsg-trier.de', [['stufenadmin', 'stage', 'biber']]);
        $person = $this->makeUser('doppelt@dpsg-trier.de', [['autor', 'own', 'biber'], ['autor', 'own', 'rover'], ['vorstand', 'global', null]]);
        $this->actingAs($stufenadmin);
        $undo = Repeater::fake();

        Livewire::test(EditUser::class, ['record' => $person->getRouteKey()])
            ->assertFormSet(['roleAssignments' => [
                'record-'.$person->roleAssignments->firstWhere('scope_key', 'biber')->id => ['assignment' => 'autor|own|biber'],
            ]])
            ->fillForm(['roleAssignments' => []])
            ->call('save')
            ->assertHasNoFormErrors();
        $undo();

        $person->refresh();
        $this->assertFalse($person->hasRoleInScope('autor', 'own', 'biber'));
        $this->assertTrue($person->hasRoleInScope('autor', 'own', 'rover'));
        $this->assertTrue($person->hasRoleInScope('vorstand', 'global', null));
        $this->assertSame('doppelt@dpsg-trier.de', $person->email);
    }

    public function test_stufenadmin_cannot_grant_roles_outside_own_scope(): void
    {
        $stufenadmin = $this->makeUser('biber-admin@dpsg-trier.de', [['stufenadmin', 'stage', 'biber']]);
        $this->actingAs($stufenadmin);
        $undo = Repeater::fake();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Versuch',
                'email' => 'versuch@dpsg-trier.de',
                'roleAssignments' => [['assignment' => 'verwaltung|global|']],
            ])
            ->call('create')
            ->assertHasFormErrors();
        $undo();

        $this->assertFalse(User::query()->where('email', 'versuch@dpsg-trier.de')->whereHas('roleAssignments')->exists());
    }

    public function test_stufenadmin_only_sees_users_in_own_scope_and_pending_ones(): void
    {
        $stufenadmin = $this->makeUser('biber-admin@dpsg-trier.de', [['stufenadmin', 'stage', 'biber']]);
        $biberAutor = $this->makeUser('biber-autor@dpsg-trier.de', [['autor', 'own', 'biber']]);
        $roverAutor = $this->makeUser('rover-autor@dpsg-trier.de', [['autor', 'own', 'rover']]);
        $verwaltung = $this->makeUser('buero@dpsg-trier.de', [['verwaltung', 'global', null]]);
        $pending = $this->makeUser('neu@dpsg-trier.de', status: 'pending');
        $this->actingAs($stufenadmin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$biberAutor, $pending])
            ->assertCanNotSeeTableRecords([$roverAutor, $verwaltung]);

        $this->assertFalse($stufenadmin->can('update', $roverAutor));
    }

    public function test_verwaltung_cannot_manage_admins_or_breakglass_account(): void
    {
        $verwaltung = $this->makeUser('buero@dpsg-trier.de', [['verwaltung', 'global', null]]);
        $admin = $this->makeUser('technik@dpsg-trier.de', [['admin', 'global', null]]);
        $breakGlass = $this->makeUser('notfall@dpsg-trier.de', [['superadmin', 'global', null]], localAdmin: true);
        $this->actingAs($verwaltung);

        $this->assertFalse($verwaltung->can('update', $admin));
        $this->assertFalse($verwaltung->can('update', $breakGlass));
        Livewire::test(ListUsers::class)->assertCanNotSeeTableRecords([$breakGlass]);
    }

    public function test_user_management_pages_render_for_verwaltung_and_are_forbidden_for_autor(): void
    {
        $verwaltung = $this->makeUser('buero@dpsg-trier.de', [['verwaltung', 'global', null]]);
        $autor = $this->makeUser('autor@dpsg-trier.de', [['autor', 'own', null]]);

        $this->actingAs($verwaltung)->get('/admin/users')->assertOk()->assertSee('autor@dpsg-trier.de');
        $this->actingAs($verwaltung)->get('/admin/users/create')->assertOk()->assertSee('M365-E-Mail-Adresse');
        $this->actingAs($verwaltung)->get("/admin/users/{$autor->id}/edit")->assertOk();

        $this->actingAs($autor)->get('/admin/users')->assertForbidden();
        $this->assertFalse($autor->can('viewAny', User::class));
    }

    public function test_disabling_user_revokes_panel_access_immediately(): void
    {
        $user = $this->makeUser('weg@dpsg-trier.de', [['autor', 'own', null]]);
        $this->assertTrue($user->canAccessPanel(Filament::getCurrentPanel()));

        $user->update(['status' => 'disabled']);

        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getCurrentPanel()));
        $this->assertTrue(AuditLog::query()->where('action', 'user.status:disabled')->where('subject_id', $user->id)->exists());
    }

    public function test_first_entra_login_links_preprovisioned_account_case_insensitively(): void
    {
        $preprovisioned = $this->makeUser('max.muster@dpsg-trier.de', [['autor', 'own', 'biber']]);

        $entraUser = (new SocialiteUser)->map([
            'id' => 'oid-123',
            'name' => 'Max Muster',
            'email' => 'Max.Muster@dpsg-trier.de',
            'user' => ['userPrincipalName' => 'Max.Muster@dpsg-trier.de'],
        ]);
        Socialite::shouldReceive('driver->user')->andReturn($entraUser);

        $this->get('/auth/entra/callback')->assertRedirect('/admin');

        $preprovisioned->refresh();
        $this->assertSame('oid-123', $preprovisioned->entra_oid);
        $this->assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($preprovisioned);
    }
}
