<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\RegistrationDocument;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Anmeldungen endgültig löschen: nur Admins, einzeln und gesammelt, samt
 * Dokumenten, ohne Mail, protokolliert.
 */
class TeilnehmendeLoeschenTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Mail::fake();
        $this->event = Event::query()->create(['title' => 'Lager', 'slug' => 'lager', 'starts_at' => now()->addMonth(), 'status' => 'open']);
    }

    private function anmeldung(string $vorname): EventRegistration
    {
        return $this->event->registrations()->create(['status' => 'confirmed', 'confirmed_at' => now(), 'first_name' => $vorname, 'last_name' => 'Test',
            'email' => strtolower($vorname).'@example.org', 'consent_text_version' => 'v1', 'consent_at' => now(), 'purge_at' => now()->addYear()]);
    }

    private function user(string $rolle): User
    {
        $u = User::query()->create(['name' => ucfirst($rolle), 'email' => $rolle.'@example.org', 'status' => 'active']);
        $u->roleAssignments()->create(['role' => $rolle, 'scope_type' => 'global']);

        return $u->fresh();
    }

    private function liste()
    {
        return Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class]);
    }

    #[TestDox('Admin: einzeln und markierte löschen, mit Dokumenten, ohne Mail, protokolliert')]
    public function test_admin(): void
    {
        $mia = $this->anmeldung('Mia');
        $mia->documents()->create(['type' => 'rechnung', 'path' => 'anmeldungen/x.pdf', 'original_name' => 'x.pdf']);
        $ben = $this->anmeldung('Ben');
        $lea = $this->anmeldung('Lea');
        $this->actingAs($this->user('admin'));

        // Auch stornierte Anmeldungen lassen sich löschen.
        $mia->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->liste()->assertActionVisible(TestAction::make('loeschen')->table($mia))
            ->callAction(TestAction::make('loeschen')->table($mia));
        $this->assertNull($mia->fresh());
        $this->assertSame(0, RegistrationDocument::query()->count());

        $this->liste()->callTableBulkAction('loeschenBulk', [$ben, $lea]);
        $this->assertSame(0, EventRegistration::query()->count());

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        $this->assertSame(['registration.geloescht:1', 'registration.geloescht:2'], AuditLog::query()->where('action', 'like', 'registration.geloescht%')->orderBy('id')->pluck('action')->all());
    }

    #[TestDox('Verwaltung sieht die Aktion nicht (nur Admins)')]
    public function test_nur_admins(): void
    {
        $mia = $this->anmeldung('Mia');
        $this->actingAs($this->user('verwaltung'));

        $this->liste()->assertActionHidden(TestAction::make('loeschen')->table($mia))
            ->assertTableBulkActionHidden('loeschenBulk');
        $this->assertNotNull($mia->fresh());
    }
}
