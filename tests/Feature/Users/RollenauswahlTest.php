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

        // Zweistufig: Rollen ohne Bereichsvarianten, Bereiche je Rolle.
        $auswahl = RoleCatalog::rollenAuswahl($alle);
        $this->assertSame('Vorstand', $auswahl['rollen']['vorstand']);
        $this->assertSame('Stufenadmin', $auswahl['rollen']['stufenadmin']);
        $this->assertSame('Autor:in', $auswahl['rollen']['autor']);
        $this->assertArrayNotHasKey('vorstand', $auswahl['bereiche']);
        $this->assertArrayHasKey('stage|woelflinge', $auswahl['bereiche']['stufenadmin']['Stufen']);
        $this->assertSame(['own|' => 'ohne Zuordnung'], $auswahl['bereiche']['autor']['Allgemein']);
        $this->assertSame('superadmin', array_key_last($auswahl['rollen']), 'Technik zuletzt');
        $this->assertSame('autor|own|', RoleCatalog::ausAuswahl('autor', 'own|'));
        $this->assertSame('vorstand|global|', RoleCatalog::ausAuswahl('vorstand', null));
    }

    #[Group('dv-trier')]
    #[TestDox('DV Trier: Bildungsreferent*in steht unter „Ganze Website“ und lässt sich vergeben')]
    public function test_bildungsreferent(): void
    {
        if (config('dpsg.site.level') === 'Stamm') {
            $this->markTestSkipped('Nur auf Ebene Bezirk/DV.');
        }
        $this->assertSame('Bildungsreferent*in', RoleCatalog::gruppiert(RoleCatalog::all())['Ganze Website']['bildungsreferent|global|']);
        $this->assertSame('Bildungsreferent*in', RoleCatalog::rollenAuswahl(RoleCatalog::all())['rollen']['bildungsreferent']);

        Notification::fake();
        Filament::setCurrentPanel('admin');
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $this->actingAs($admin->fresh());
        $undo = Repeater::fake();

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Bildungsreferentin', 'email' => 'br@example.org', 'status' => 'active', 'roleAssignments' => [['rolle' => 'bildungsreferent']]])
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();

        $this->assertTrue(User::query()->where('email', 'br@example.org')->sole()->isBildungsreferent());
    }

    #[TestDox('Bereichsrolle ohne Bereich wird abgelehnt; doppelte Zuweisung auch')]
    public function test_pruefung(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $this->actingAs($admin->fresh());
        $undo = Repeater::fake();
        $basis = ['name' => 'Leitung', 'email' => 'leitung@example.org', 'status' => 'active'];

        Livewire::test(CreateUser::class)
            ->fillForm([...$basis, 'roleAssignments' => [['rolle' => 'stufenadmin']]])
            ->call('create')
            ->assertHasFormErrors(['roleAssignments.0.bereich' => 'required']);
        Livewire::test(CreateUser::class)
            ->fillForm([...$basis, 'roleAssignments' => [['rolle' => 'stufenadmin', 'bereich' => 'stage|woelflinge'], ['rolle' => 'stufenadmin', 'bereich' => 'stage|woelflinge']]])
            ->call('create')
            ->assertHasFormErrors(['roleAssignments']);
        Livewire::test(CreateUser::class)
            ->fillForm([...$basis, 'roleAssignments' => [['rolle' => 'stufenadmin', 'bereich' => 'stage|woelflinge'], ['rolle' => 'autor', 'bereich' => 'own|']]])
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();

        $this->assertEqualsCanonicalizing(['stufenadmin|stage|woelflinge', 'autor|own|'],
            User::query()->where('email', 'leitung@example.org')->sole()->roleAssignments->map(fn ($a) => RoleCatalog::encode($a))->all());
    }
}
