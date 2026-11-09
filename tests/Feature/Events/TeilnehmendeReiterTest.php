<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Mail\AnmeldungInfoMail;
use App\Mail\EventRegistrationMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Veranstaltung bearbeiten: Reiter „Veranstaltung“ und „Teilnehmende“;
 * unbestätigte Anmeldungen von Hand bestätigen.
 */
class TeilnehmendeReiterTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->event = Event::query()->create(['title' => 'Aktion', 'slug' => 'aktion', 'starts_at' => now()->addMonth(), 'status' => 'open', 'adults_only' => true]);
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());
    }

    private function anmeldung(string $vorname, string $status = 'pending'): EventRegistration
    {
        return $this->event->registrations()->create([
            'status' => $status, 'first_name' => $vorname, 'last_name' => 'Muster', 'email' => mb_strtolower($vorname).'@example.org',
            'consent_text_version' => 'v1', 'consent_at' => now(), 'confirm_token_hash' => hash('sha256', $vorname),
        ]);
    }

    #[TestDox('Reiter oben: „Veranstaltung“ und „Teilnehmende“ mit Anzahl; ?relation=0 öffnet gleich die Teilnehmenden')]
    public function test_reiter(): void
    {
        $this->anmeldung('Kim');
        $this->anmeldung('Ben', 'cancelled');

        $this->get(EventResource::getUrl('edit', ['record' => $this->event]))->assertOk()
            ->assertSeeInOrder(['Veranstaltung', 'Teilnehmende', 'Fragen']);
        $this->assertSame('1', RegistrationsRelationManager::getBadge($this->event, EditEvent::class));

        Livewire::withQueryParams(['relation' => '0'])->test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->assertSet('activeRelationManager', '0')
            ->assertSeeLivewire(RegistrationsRelationManager::class);
    }

    #[TestDox('Von Hand bestätigen: einzeln mit Mail und Info ans Team, mehrere auf einmal; volle Veranstaltung mit Warteliste; Bestätigte bleiben unberührt')]
    public function test_bestaetigen(): void
    {
        $kim = $this->anmeldung('Kim');
        $rm = Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class]);

        $rm->assertTableActionVisible('confirmManually', $kim)
            ->callTableAction('confirmManually', $kim, ['status' => 'auto', 'notify' => true])
            ->assertNotified('Anmeldung bestätigt (Bestätigt).');
        $kim->refresh();
        $this->assertSame(['confirmed', null], [$kim->status, $kim->confirm_token_hash]);
        $this->assertNotNull($kim->confirmed_at);
        $this->assertNotNull($kim->cancel_token_hash);
        Mail::assertQueued(EventRegistrationMail::class, fn (EventRegistrationMail $m) => $m->kind === 'confirmed' && $m->hasTo('kim@example.org') && filled($m->token));
        Mail::assertQueued(AnmeldungInfoMail::class);
        $this->assertSame(1, AuditLog::query()->where('action', 'registration.confirm_manual')->count());
        $rm->assertTableActionHidden('confirmManually', $kim);

        // Voll mit Warteliste: „auto“ setzt auf die Warteliste; ohne Mail.
        $this->event->update(['capacity' => 1, 'waitlist_enabled' => true]);
        $ben = $this->anmeldung('Ben');
        $tom = $this->anmeldung('Tom');
        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event->fresh(), 'pageClass' => EditEvent::class])
            ->callTableBulkAction('confirmManuallyBulk', [$ben, $tom, $kim], ['status' => 'auto', 'notify' => false])
            ->assertNotified('2 Anmeldungen bestätigt.');
        $this->assertSame(['waitlist', 'waitlist', 'confirmed'], [$ben->fresh()->status, $tom->fresh()->status, $kim->fresh()->status]);
        Mail::assertNotQueued(EventRegistrationMail::class, fn (EventRegistrationMail $m) => $m->hasTo('ben@example.org'));

        // Über die Platzzahl hinaus ausdrücklich bestätigen.
        $lea = $this->anmeldung('Lea');
        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event->fresh(), 'pageClass' => EditEvent::class])
            ->callTableAction('confirmManually', $lea, ['status' => 'confirmed', 'notify' => true]);
        $this->assertSame('confirmed', $lea->fresh()->status);
    }
}
