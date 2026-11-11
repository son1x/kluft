<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\ParticipantExport;
use App\Services\TeilnehmendeListePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Sensible Formularfelder (z. B. Gesundheit): Hinweis im Formular, nicht
 * im Standard-Export und früh gelöscht (docs/datenschutz-ueberblick.md).
 */
class SensibleFelderTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $extra = []): Event
    {
        return Event::query()->create([
            'title' => 'Zeltlager', 'slug' => 'zeltlager', 'starts_at' => now()->addMonth(), 'status' => 'open', 'adults_only' => true,
            'form_fields' => ['eigene' => [
                ['schluessel' => 'eigen_allergien', 'label' => 'Allergien', 'typ' => 'text', 'sensibel' => true],
                ['schluessel' => 'eigen_shirt', 'label' => 'T-Shirt-Größe', 'typ' => 'text'],
            ]],
            ...$extra,
        ]);
    }

    private function anmeldung(Event $event): EventRegistration
    {
        return $event->registrations()->create([
            'status' => 'confirmed', 'confirmed_at' => now(), 'consent_text_version' => 'v1', 'consent_at' => now(), 'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'mia@example.org',
            'data' => ['eigen_allergien' => 'Nüsse', 'eigen_shirt' => 'M'], 'purge_at' => now()->addYear(),
        ]);
    }

    #[TestDox('Formular zeigt den Schutzhinweis, Einwilligung nennt die Frist')]
    public function test_formular(): void
    {
        $event = $this->event();
        $this->assertSame(['eigen_allergien'], $event->sensibleFelder());
        $this->get('/veranstaltungen/zeltlager')->assertOk()->assertSee('Besonders geschützt');
        $this->assertStringContainsString('14 Tage nach der Veranstaltung', $event->consentText());
    }

    #[TestDox('Export und PDF-Liste: sensible Angaben nur auf ausdrücklichen Wunsch, gesondert protokolliert')]
    public function test_export(): void
    {
        $event = $this->event();
        $this->anmeldung($event);
        $export = app(ParticipantExport::class);

        $this->assertNotContains('Allergien', $export->headings($event));
        $this->assertContains('T-Shirt-Größe', $export->headings($event));
        $this->assertContains('Allergien', $export->headings($event, true));
        $this->assertContains('Nüsse', $export->rows($event, true)[0]);
        $this->assertSame('Allergien (sensibel)', TeilnehmendeListePdf::felder($event)['eigen_allergien']);
        $this->assertNotContains('eigen_allergien', TeilnehmendeListePdf::standardFelder($event));

        Mail::fake();
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($user->fresh());

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('export', data: ['sensibel' => true])->assertFileDownloaded();
        $this->assertSame(1, AuditLog::query()->where('action', 'event.export.sensibel')->count());
    }

    #[TestDox('Löschlauf: sensible Angaben verschwinden 14 Tage nach der Veranstaltung, der Rest bleibt')]
    public function test_loeschen(): void
    {
        $event = $this->event(['starts_at' => now()->subDays(20), 'ends_at' => now()->subDays(16)]);
        $offen = $this->event(['slug' => 'offen', 'starts_at' => now()->subDays(5)]);
        $r = $this->anmeldung($event);
        $o = $this->anmeldung($offen);

        $this->artisan('registrations:sensible-loeschen')->assertSuccessful();

        $this->assertSame(['eigen_shirt' => 'M'], $r->fresh()->data);
        $this->assertSame('Nüsse', $o->fresh()->data['eigen_allergien']);
        $this->assertNotNull($event->fresh()->sensible_geloescht_at);
        $this->assertNull($offen->fresh()->sensible_geloescht_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'event.sensibel_geloescht:1')->count());
    }
}
