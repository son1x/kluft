<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\Services\EntraDirectory;
use App\Support\PersonenSuche;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Veranstaltung: „Verantwortlich“ und „Kontaktperson“ per Suche aus
 * Benutzern, Personen und (nur Admins mit Verbindung) M365 übernehmen.
 */
class VerantwortlichSucheTest extends TestCase
{
    use RefreshDatabase;

    private const M365 = '99999999-8888-7777-6666-555555555555';

    private function user(string $name, string $email, string $role): User
    {
        $u = User::query()->create(['name' => $name, 'email' => $email, 'status' => 'active', 'entra_oid' => $role === 'admin' ? '11111111-2222-3333-4444-555555555555' : null]);
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

    #[TestDox('M365 nur für Admins mit Verbindung; Bildungsreferent*in sucht nie im Verzeichnis')]
    public function test_m365_nur_admins(): void
    {
        Http::fake(['graph.microsoft.com/*' => Http::response(['value' => [
            ['id' => self::M365, 'displayName' => 'Till Mang', 'mail' => 'T.Mang@dpsg-trier.de', 'userPrincipalName' => 't.mang@dpsg-trier.de'],
        ]])]);
        app(EntraDirectory::class)->remember('token', 3600);

        $br = $this->user('Julia Lehn', 'julia@example.org', 'bildungsreferent');
        $this->assertSame([], PersonenSuche::suchen('Till', $br));
        Http::assertNothingSent();

        $admin = $this->user('Admin', 'admin@example.org', 'admin');
        $treffer = PersonenSuche::suchen('Till', $admin);
        $this->assertSame(['m:'.self::M365 => 'Till Mang – t.mang@dpsg-trier.de (M365)'], $treffer);
        $this->assertSame(['name' => 'Till Mang', 'email' => 't.mang@dpsg-trier.de'], PersonenSuche::finden('m:'.self::M365, $admin));
        $this->assertNull(PersonenSuche::finden('m:'.self::M365, $br));
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

    #[TestDox('Nach dem Verbinden mit Microsoft zurück zur Veranstaltung (nur Backend-Adressen)')]
    public function test_rueckweg(): void
    {
        $admin = $this->user('Admin', 'admin@example.org', 'admin');
        $this->actingAs($admin);
        $this->get('/auth/entra/verzeichnis?zurueck='.urlencode(url('/admin/events/1/edit')))->assertRedirect();
        $this->assertSame(url('/admin/events/1/edit'), session('entra.zurueck'));
        $this->get('/auth/entra/verzeichnis?zurueck='.urlencode('https://boese.example/admin/x'))->assertRedirect();
        $this->assertNull(session('entra.zurueck'));
    }
}
