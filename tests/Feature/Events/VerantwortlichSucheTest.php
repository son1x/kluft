<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\Support\PersonenSuche;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Veranstaltung: „Verantwortlich“ und „Kontaktperson“ per Suche aus
 * Benutzerkonten und Personen der Website übernehmen.
 */
class VerantwortlichSucheTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $email, string $role): User
    {
        $u = User::query()->create(['name' => $name, 'email' => $email, 'status' => 'active']);
        $u->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);

        return $u->fresh();
    }

    #[TestDox('Suche findet Benutzer und Personen (ohne doppelte Adressen), nicht gesperrte Konten')]
    public function test_suche(): void
    {
        $lisa = $this->user('Lisa Alm', 'lisa.alm@example.org', 'bildungsreferent');
        User::query()->create(['name' => 'Lisa Alt', 'email' => 'alt@example.org', 'status' => 'disabled']);
        Person::query()->create(['gremium' => 'dl', 'name' => 'Lisa Alm', 'email' => 'lisa.alm@example.org', 'active' => true, 'consent' => true]);
        $marc = Person::query()->create(['gremium' => 'dl', 'name' => 'Marc Lisanto', 'email' => 'marc@example.org', 'active' => true, 'consent' => true]);

        $treffer = PersonenSuche::suchen('Lisa', $lisa);
        $this->assertSame(['u:'.$lisa->id, 'p:'.$marc->id], array_keys($treffer));
        $this->assertSame('Lisa Alm – lisa.alm@example.org (Benutzer)', $treffer['u:'.$lisa->id]);
        $this->assertSame([], PersonenSuche::suchen('L', $lisa));
        $this->assertSame(['name' => 'Marc Lisanto', 'email' => 'marc@example.org'], PersonenSuche::finden('p:'.$marc->id, $lisa));
    }

    #[TestDox('Im Formular: Lupe übernimmt die E-Mail bei „Verantwortlich“ und nur den Namen bei der Kontaktperson')]
    public function test_formular(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = $this->user('Admin', 'admin@example.org', 'admin');
        $lisa = $this->user('Lisa Alm', 'lisa.alm@example.org', 'bildungsreferent');
        $event = Event::query()->create(['title' => 'Lager', 'slug' => 'lager', 'starts_at' => now()->addMonth(), 'status' => 'open', 'retention_days' => 90,
            'contact_email' => 'woelflinge@example.org', 'created_by' => $admin->id]);
        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->callAction(TestAction::make('suchen_verantwortlich')->schemaComponent('verantwortlich_email'), ['person' => 'u:'.$lisa->id])
            ->assertFormSet(['verantwortlich_email' => 'lisa.alm@example.org'])
            ->callAction(TestAction::make('suchen_kontakt')->schemaComponent('contact_name'), ['person' => 'u:'.$lisa->id])
            ->assertFormSet(['contact_name' => 'Lisa Alm', 'contact_email' => 'woelflinge@example.org']);
    }
}
