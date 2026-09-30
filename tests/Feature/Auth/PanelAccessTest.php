<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pflichtenheft 13, Abnahmekriterium 1 (nur zugewiesene Rechte) und 16
 * (Konto ohne Zuweisung hat keinen Zugriff).
 */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_user_without_role_cannot_access_admin_panel(): void
    {
        $user = User::query()->create([
            'name' => 'Wartet auf Freischaltung',
            'email' => 'pending@dpsg-trier.de',
            'entra_oid' => 'oid-pending',
            'status' => 'pending',
        ]);

        $this->assertFalse($user->canAccessPanel(filament()->getCurrentOrDefaultPanel()));
    }

    public function test_active_user_with_role_can_access_admin_panel(): void
    {
        $user = User::query()->create([
            'name' => 'Stufenadmin Biber',
            'email' => 'biber@dpsg-trier.de',
            'entra_oid' => 'oid-active',
            'status' => 'active',
        ]);

        $user->roleAssignments()->create([
            'role' => 'stufenadmin',
            'scope_type' => 'stage',
            'scope_key' => 'biber',
        ]);

        $this->assertTrue($user->canAccessPanel(filament()->getCurrentOrDefaultPanel()));
    }

    public function test_disabled_user_cannot_access_admin_panel_even_with_role(): void
    {
        $user = User::query()->create([
            'name' => 'Ehemalige Redakteurin',
            'email' => 'ex@dpsg-trier.de',
            'entra_oid' => 'oid-disabled',
            'status' => 'disabled',
        ]);

        $user->roleAssignments()->create([
            'role' => 'autor',
            'scope_type' => 'own',
        ]);

        $this->assertFalse($user->canAccessPanel(filament()->getCurrentOrDefaultPanel()));
    }

    public function test_scope_helper_distinguishes_stage_assignments(): void
    {
        $user = User::query()->create([
            'name' => 'Stufenadmin Wölflinge',
            'email' => 'woelflinge@dpsg-trier.de',
            'status' => 'active',
        ]);

        $user->roleAssignments()->create([
            'role' => 'stufenadmin',
            'scope_type' => 'stage',
            'scope_key' => 'woelflinge',
        ]);

        $this->assertTrue($user->hasRoleInScope('stufenadmin', 'stage', 'woelflinge'));
        $this->assertFalse($user->hasRoleInScope('stufenadmin', 'stage', 'biber'));
    }
}
