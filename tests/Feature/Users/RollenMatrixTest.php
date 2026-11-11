<?php

namespace Tests\Feature\Users;

use App\Filament\Pages\RollenMatrix;
use App\Filament\Resources\Pages\PageResource;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Rolle;
use App\Models\User;
use App\Support\Rechte;
use App\Support\RoleCatalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Rechte-Matrix: eigene Rollen aus Vorlagen, Häkchen, Geltung je Bereich,
 * Vergabe und Wirkung.
 */
class RollenMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    /** @param  list<array{0: string, 1: string, 2: ?string}>  $rollen */
    private function user(string $email, array $rollen): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        foreach ($rollen as [$role, $type, $key]) {
            $user->roleAssignments()->create(['role' => $role, 'scope_type' => $type, 'scope_key' => $key]);
        }

        return $user->fresh();
    }

    #[TestDox('Nur Admins sehen die Matrix; Vorlagen zeigen die mitgelieferten Rollen')]
    public function test_zugriff(): void
    {
        $this->actingAs($this->user('buero@example.org', [['verwaltung', 'global', null]]));
        $this->get(RollenMatrix::getUrl())->assertForbidden();

        $this->actingAs($this->user('admin@example.org', [['admin', 'global', null]]));
        $this->get(RollenMatrix::getUrl())->assertOk()->assertSee('Rollen und Rechte')->assertSee('Stufenadmin')->assertSee('Veranstaltungen bearbeiten');
    }

    #[TestDox('Neue Rolle aus Vorlage „Sektionsadmin“, ohne Veranstaltungen, je Bereich; Häkchen umschalten, Bereichsfremdes gesperrt; Protokoll')]
    public function test_rolle_anlegen_und_haken(): void
    {
        $this->actingAs($this->user('admin@example.org', [['admin', 'global', null]]));

        Livewire::test(RollenMatrix::class)
            ->callAction('neu', ['name' => 'Redaktion Fachbereich', 'vorlage' => 'sektionsadmin', 'geltung' => 'bereich', 'beschreibung' => 'Texte des Fachbereichs'])
            ->assertNotified('Rolle „Redaktion Fachbereich“ angelegt');
        $rolle = Rolle::query()->sole();
        $this->assertStringStartsWith(Rechte::EIGEN, $rolle->schluessel);
        $this->assertSame('bereich', $rolle->geltung);
        $this->assertContains('seiten', $rolle->rechte);

        $lw = Livewire::test(RollenMatrix::class);
        foreach (['veranstaltungen.lesen', 'veranstaltungen.bearbeiten'] as $r) {
            if (in_array($r, $rolle->fresh()->rechte, true)) {
                $lw->call('umschalten', $rolle->id, $r);
            }
        }
        $lw->call('umschalten', $rolle->id, 'kasse')->assertNotified('Gilt nur für die ganze Website');
        $rechte = $rolle->fresh()->rechte;
        $this->assertNotContains('veranstaltungen.lesen', $rechte);
        $this->assertNotContains('kasse', $rechte);
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'like', 'rolle.rechte:%')->count());

        $lw->call('umschalten', $rolle->id, 'nachrichten')->call('umschalten', $rolle->id, 'nachrichten');
        $this->assertContains('nachrichten', $rolle->fresh()->rechte);
    }

    #[TestDox('Vergabe je Bereich: „Name – Wölflinge“; wirkt nur dort; sensible Rollen gelten als weitreichend; Löschen erst ohne Zuweisungen')]
    public function test_vergabe_und_wirkung(): void
    {
        $bereich = 'woelflinge';
        $rolle = Rolle::query()->create(['name' => 'Redaktion Fachbereich', 'geltung' => 'bereich', 'rechte' => ['seiten', 'beitraege', 'kasse']]);
        $this->assertSame(['seiten', 'beitraege'], $rolle->fresh()->rechte, 'Bereichsfremdes Recht wird verworfen');
        $this->assertArrayHasKey("{$rolle->schluessel}|stage|{$bereich}", RoleCatalog::all());
        $this->assertFalse(RoleCatalog::isPrivileged("{$rolle->schluessel}|stage|{$bereich}"));

        $kasse = Rolle::query()->create(['name' => 'Kassenprüfung', 'geltung' => 'global', 'rechte' => ['kasse']]);
        $this->assertTrue(RoleCatalog::isPrivileged("{$kasse->schluessel}|global|"));

        $ed = $this->user('ed@example.org', [[$rolle->schluessel, 'stage', $bereich]]);
        $this->assertTrue($ed->darf('seiten', 'stage', $bereich));
        $this->assertFalse($ed->darf('seiten', 'stage', 'rover'));
        $this->assertFalse($ed->darfUeberall('seiten'));
        $this->assertFalse($ed->darf('veranstaltungen.lesen'));
        $this->actingAs($ed);
        $this->assertTrue(PageResource::canCreate());
        $this->assertFalse($ed->can('create', Event::class));

        $this->actingAs($this->user('admin@example.org', [['admin', 'global', null]]));
        Livewire::test(RollenMatrix::class)->callAction('loeschen', arguments: ['rolle' => $rolle->id])->assertNotified('Noch vergeben');
        $this->assertNotNull($rolle->fresh());
    }
}
