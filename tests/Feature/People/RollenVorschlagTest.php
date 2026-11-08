<?php

namespace Tests\Feature\People;

use App\Filament\Resources\People\Pages\ListPeople;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\User;
use App\Notifications\RolesGrantedNotification;
use App\Support\RollenVorschlag;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Ansprechpersonen und Stufenreferent*innen mit verknüpftem Konto bekommen
 * die Rolle für ihren Bereich vorgeschlagen; die Verwaltung bestätigt.
 */
class RollenVorschlagTest extends TestCase
{
    use RefreshDatabase;

    private ?string $bereich;

    private string $stufe;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Notification::fake();
        // Stämme haben keine Fachbereiche: dann nur der Stufen-Teil.
        $this->bereich = array_key_first((array) config('dpsg.sections'));
        $this->stufe = (string) array_key_first((array) config('dpsg.stages'));
    }

    /** @return list<string> */
    private function bereiche(): array
    {
        return $this->bereich === null ? [] : [$this->bereich];
    }

    private function user(string $email, ?string $role = null): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        if ($role) {
            $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);
        }

        return $user->fresh();
    }

    private function person(User $user, array $attrs): Person
    {
        return Person::query()->create(['gremium' => 'leitung', 'name' => $user->name, 'consent' => true, 'active' => true, 'sort' => 1, 'user_id' => $user->id, ...$attrs]);
    }

    #[TestDox('Vorschlag: Ansprechperson → Sektionsadmin, Stufenreferent*in → Stufenadmin; nicht ohne Konto, nicht doppelt, nicht für Arbeitskreis und Verwaltung')]
    public function test_vorschlag(): void
    {
        $lea = $this->user('lea@example.org');
        $person = $this->person($lea, ['sections' => $this->bereiche(), 'stage' => $this->stufe, 'role' => 'Referentin']);
        $vorschlag = fn (Person $p) => array_keys((new RollenVorschlag)->fuer($p->fresh()));

        $this->assertSame([...$this->bereich ? ["sektionsadmin|section|{$this->bereich}"] : [], "stufenadmin|stage|{$this->stufe}"], $vorschlag($person));

        // Rolle schon da: nicht noch einmal.
        $lea->roleAssignments()->create(['role' => 'stufenadmin', 'scope_type' => 'stage', 'scope_key' => $this->stufe]);
        $this->assertNotContains("stufenadmin|stage|{$this->stufe}", $vorschlag($person));

        // Ohne Konto, Arbeitskreis (DAK) und Verwaltung: nichts.
        $this->assertSame([], $vorschlag(Person::query()->create(['gremium' => 'leitung', 'name' => 'Ohne', 'stage' => $this->stufe, 'sections' => $this->bereiche(), 'active' => true, 'sort' => 2])));
        $this->assertSame([], $vorschlag($this->person($this->user('dak@example.org'), ['gremium' => 'dak', 'stage' => $this->stufe])));
        $this->assertSame([], $vorschlag($this->person($this->user('buero@example.org', 'verwaltung'), ['stage' => $this->stufe, 'sections' => $this->bereiche()])));
    }

    #[TestDox('Rollen-Vorschläge prüfen: nur Angehaktes wird vergeben, mit Mail an die Person und Audit-Eintrag')]
    public function test_vergeben(): void
    {
        $lea = $this->user('lea@example.org');
        $this->person($lea, ['sections' => $this->bereiche(), 'stage' => $this->stufe, 'role' => 'Referentin']);
        $this->actingAs($this->user('verwaltung@example.org', 'verwaltung'));
        $stufenadmin = "stufenadmin|stage|{$this->stufe}";

        // Nicht Vorgeschlagenes (z. B. Super-Admin) lässt sich nicht einschmuggeln.
        Livewire::test(ListPeople::class)
            ->callAction('rollenVorschlaege', ['rollen' => ["{$lea->id}#superadmin|global|"]]);
        $this->assertFalse($lea->fresh()->isSuperAdmin());

        Livewire::test(ListPeople::class)
            ->assertActionVisible('rollenVorschlaege')
            ->callAction('rollenVorschlaege', ['rollen' => ["{$lea->id}#{$stufenadmin}"]])
            ->assertNotified('1 Rolle vergeben');

        $lea = $lea->fresh();
        $this->assertTrue($lea->hasRoleInScope('stufenadmin', 'stage', $this->stufe));
        $this->assertFalse($lea->hasRole('sektionsadmin'));
        Notification::assertSentTo($lea, RolesGrantedNotification::class, fn ($n) => $n->assignments === [$stufenadmin]);
        $this->assertTrue(AuditLog::query()->where('subject_id', $lea->id)->where('action', "role.granted:{$stufenadmin}")->exists());

        if ($this->bereich !== null) {
            Livewire::test(ListPeople::class)
                ->callAction('rollenVorschlaege', ['rollen' => ["{$lea->id}#sektionsadmin|section|{$this->bereich}"]])
                ->assertNotified('1 Rolle vergeben');
            $this->assertTrue($lea->fresh()->hasRoleInScope('sektionsadmin', 'section', $this->bereich));
        }

        // Nichts mehr offen.
        Livewire::test(ListPeople::class)->assertActionHidden('rollenVorschlaege');
    }
}
