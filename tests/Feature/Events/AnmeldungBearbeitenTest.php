<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Support\RoleCatalog;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Angaben einer Anmeldung nachträglich bearbeiten – z. B. nur einzelne Tage.
 */
class AnmeldungBearbeitenTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Mail::fake();
        $this->event = Event::query()->create(['title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'starts_at' => '2027-05-14 10:00', 'ends_at' => '2027-05-17 14:00',
            'status' => 'open', 'stage' => $this->stufe(), 'form_fields' => ['tage' => 'optional', 'diet' => 'optional', 'comment' => 'optional'], 'zusatz_felder' => ['Zimmer']]);
    }

    /** Erste Stufe der Instanz (die Instanzen haben unterschiedliche Stufen). */
    private function stufe(): string
    {
        return (string) array_key_first(array_diff_key(Event::stufen(), [Event::LEITENDE => true]));
    }

    private function anmeldung(): EventRegistration
    {
        return $this->event->registrations()->create(['status' => 'confirmed', 'confirmed_at' => now(), 'first_name' => 'Mia', 'last_name' => 'Test',
            'email' => 'mia@example.org', 'data' => ['tage' => ['2027-05-14', '2027-05-15', '2027-05-16', '2027-05-17'], 'diet' => 'alles', 'intern' => 'bleibt'],
            'consent_text_version' => 'v1', 'consent_at' => now(), 'purge_at' => now()->addYear()]);
    }

    private function user(string $rolle, string $typ = 'global', ?string $key = null): User
    {
        $u = User::query()->create(['name' => ucfirst($rolle), 'email' => $rolle.'@example.org', 'status' => 'active']);
        $u->roleAssignments()->create(['role' => $rolle, 'scope_type' => $typ, 'scope_key' => $key]);

        return $u->fresh();
    }

    private function liste()
    {
        return Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class]);
    }

    #[TestDox('Stufenverantwortliche ändern Tage, Verpflegung, Name und Anmerkung; andere Angaben bleiben, keine Mail, protokolliert')]
    public function test_bearbeiten(): void
    {
        $mia = $this->anmeldung();
        $this->actingAs($this->user(RoleCatalog::leitungLiestVeranstaltungen() ? 'veranstaltungen' : 'stufenadmin', 'stage', $this->stufe()));

        $this->liste()
            ->mountAction(TestAction::make('bearbeiten')->table($mia))
            ->assertSchemaStateSet(['first_name' => 'Mia', 'data.tage' => ['2027-05-14', '2027-05-15', '2027-05-16', '2027-05-17']]);
        $this->liste()->callAction(TestAction::make('bearbeiten')->table($mia), [
            'first_name' => 'Mia-Sophie', 'last_name' => 'Test', 'email' => 'mia@example.org',
            'data' => ['tage' => ['2027-05-15', '2027-05-16'], 'diet' => 'vegan', 'comment' => 'Kommt erst Samstag'], 'zusatz' => ['zimmer' => '12'],
        ])->assertHasNoFormErrors();

        $mia->refresh();
        $this->assertSame('Mia-Sophie', $mia->first_name);
        $this->assertSame(['2027-05-15', '2027-05-16'], $mia->data['tage']);
        $this->assertSame('vegan', $mia->data['diet']);
        $this->assertSame(['zimmer' => '12'], $mia->zusatz);
        $this->assertSame('Kommt erst Samstag', $mia->data['comment']);
        $this->assertSame('bleibt', $mia->data['intern']);
        Mail::assertNothingSent();
        $this->assertTrue(AuditLog::query()->where('action', 'registration.bearbeitet:'.$mia->getKey())->exists());
    }

    #[TestDox('Wer nur lesen darf, sieht „Angaben bearbeiten“ nicht')]
    public function test_nur_lesen(): void
    {
        $mia = $this->anmeldung();
        $this->actingAs($this->user('vorstand'));
        $this->liste()->assertActionDoesNotExist(TestAction::make('bearbeiten')->table($mia));
    }
}
