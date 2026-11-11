<?php

namespace Tests\Feature\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use App\Support\RoleCatalog;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Rollenauswahl im Benutzerformular: gruppiert und durchsuchbar, Rollen
 * für die ganze Website zuerst.
 */
class RollenauswahlTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Gruppen: ganze Website zuerst, Technik zuletzt; nichts geht verloren')]
    public function test_gruppen(): void
    {
        $alle = RoleCatalog::all();
        $gruppen = RoleCatalog::gruppiert($alle);

        $this->assertSame('Ganze Website', array_key_first($gruppen));
        $this->assertSame('Technik', array_key_last($gruppen));
        $this->assertArrayHasKey('vorstand|global|', $gruppen['Ganze Website']);
        $this->assertArrayHasKey('admin|global|', $gruppen['Technik']);
        $this->assertSame(count($alle), array_sum(array_map('count', $gruppen)));
    }

    #[Group('dv-trier')]
    #[TestDox('DV Trier: Bildungsreferent*in steht unter „Ganze Website“ und lässt sich vergeben')]
    public function test_bildungsreferent(): void
    {
        if (config('dpsg.site.level') === 'Stamm') {
            $this->markTestSkipped('Nur auf Ebene Bezirk/DV.');
        }
        $this->assertSame('Bildungsreferent*in', RoleCatalog::gruppiert(RoleCatalog::all())['Ganze Website']['bildungsreferent|global|']);

        Notification::fake();
        Filament::setCurrentPanel('admin');
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $this->actingAs($admin->fresh());
        $undo = Repeater::fake();

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Bildungsreferentin', 'email' => 'br@example.org', 'status' => 'active', 'roleAssignments' => [['assignment' => 'bildungsreferent|global|']]])
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();

        $this->assertTrue(User::query()->where('email', 'br@example.org')->sole()->isBildungsreferent());
    }
}
