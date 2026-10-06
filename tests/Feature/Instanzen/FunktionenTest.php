<?php

namespace Tests\Feature\Instanzen;

use App\Filament\Pages\Funktionen;
use App\Filament\Pages\NamiZugang;
use App\Filament\Pages\PushSettings;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\FeatureSwitch;
use App\Models\Gruppierung;
use App\Models\User;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Verwaltung → Funktionen: Funktionen im Backend an- und abschalten.
 */
class FunktionenTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role): User
    {
        $user = User::query()->create(['name' => 'Test '.$role, 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global', 'scope_key' => null]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    #[TestDox('Nur Admins sehen die Seite; Schalter überstimmt den Standard, Rückkehr zum Standard entfernt ihn; Protokoll')]
    public function test_page_and_switching(): void
    {
        $this->login('verwaltung');
        $this->get(Funktionen::getUrl())->assertForbidden();

        $admin = $this->login('admin');
        $this->get(Funktionen::getUrl())->assertOk()->assertSee('Teilnehmende von Hand eintragen')->assertSee('Standard dieser Website: an');

        $this->assertTrue(Features::enabled(Features::TEILNEHMENDE_EINTRAGEN));
        Livewire::test(Funktionen::class)
            ->set('data.schalter.'.Features::TEILNEHMENDE_EINTRAGEN, false)
            ->call('save')
            ->assertNotified();
        $this->assertFalse(Features::enabled(Features::TEILNEHMENDE_EINTRAGEN));
        $this->assertSame($admin->id, FeatureSwitch::query()->find(Features::TEILNEHMENDE_EINTRAGEN)?->updated_by);
        $this->assertTrue(AuditLog::query()->where('action', 'feature.aus:'.Features::TEILNEHMENDE_EINTRAGEN)->exists());

        Livewire::test(Funktionen::class)
            ->assertSet('data.schalter.'.Features::TEILNEHMENDE_EINTRAGEN, false)
            ->set('data.schalter.'.Features::TEILNEHMENDE_EINTRAGEN, true)
            ->call('save');
        $this->assertTrue(Features::enabled(Features::TEILNEHMENDE_EINTRAGEN));
        $this->assertNull(FeatureSwitch::query()->find(Features::TEILNEHMENDE_EINTRAGEN), 'gleich dem Standard: kein Schalter');
    }

    #[TestDox('Abgeschaltet verschwinden: Eintragen, Bescheinigung, Gäste-Kästchen, „Nur für Mitglieder“, Push, NaMi-Profil')]
    public function test_switched_off_features_disappear(): void
    {
        $this->login('admin');
        $stamm = Gruppierung::query()->create(['typ' => 'stamm', 'name' => 'Sankt Konrad', 'slug' => 'st-konrad-test', 'active' => true]);
        $event = Event::query()->create([
            'title' => 'Stammeslager', 'slug' => 'stammeslager', 'status' => 'open', 'starts_at' => now()->addMonth(),
            'form_fields' => ['stamm' => 'optional'], 'certificate_enabled' => true, 'member_gruppierung_id' => $stamm->id,
        ]);
        $tabelle = fn () => Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event->fresh(), 'pageClass' => EditEvent::class]);

        $tabelle()->assertTableActionVisible('addParticipant')->assertTableBulkActionVisible('approveCertificates');
        $this->assertArrayHasKey('herkunft', $event->formFields());
        $this->assertSame('Mitglieder von Stamm Sankt Konrad', $event->zielgruppeMitglieder());

        foreach ([Features::TEILNEHMENDE_EINTRAGEN, Features::TEILNAHMEBESCHEINIGUNG, Features::GAESTE, Features::NUR_MITGLIEDER, Features::PUSH, Features::NAMI_PROFIL] as $f) {
            Features::setzen($f, false);
        }

        $tabelle()->assertTableActionHidden('addParticipant')->assertTableBulkActionHidden('approveCertificates');
        $event->refresh();
        $this->assertFalse($event->bescheinigungAn());
        $this->assertArrayNotHasKey('herkunft', $event->formFields());
        $this->assertNull($event->zielgruppeMitglieder());
        $this->get('/veranstaltungen/stammeslager')->assertOk()->assertDontSee('Mitglieder von Stamm Sankt Konrad')->assertDontSee('name="herkunft"', false);
        $this->assertFalse(PushSettings::canAccess());
        $this->assertFalse(NamiZugang::canAccess());

        $user = auth()->user();
        $user->forceFill(['ntfy_topic' => 'x', 'ntfy_events' => ['anmeldungen']])->save();
        $this->assertFalse($user->fresh()->wantsPush('anmeldungen'));
    }
}
