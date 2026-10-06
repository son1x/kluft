<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Mail\ParticipantCodeMail;
use App\Mail\TeilnahmebescheinigungMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Teilnahmebescheinigung;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Teilnahmebescheinigung: Freigabe durch die Veranstaltungsleitung (einzeln
 * oder als Massenaktion), PDF mit Prüfcode, Echtheitsprüfung.
 */
class TeilnahmebescheinigungTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->event = Event::query()->create([
            'title' => 'Woodbadge-Kurs Modul 1b', 'slug' => 'woodbadge-1b', 'status' => 'open', 'location' => 'Zell',
            'starts_at' => now()->subDays(3)->setTime(18, 0), 'ends_at' => now()->subDay()->setTime(14, 0),
            'certificate_enabled' => true, 'certificate_text' => '20 Unterrichtseinheiten', 'certificate_signer' => 'Anna Beispiel, Diözesanvorsitzende',
        ]);
    }

    private function anmeldung(string $vorname, string $nachname, string $status = 'confirmed', string $email = 'eltern@example.org'): EventRegistration
    {
        return $this->event->registrations()->create([
            'status' => $status, 'first_name' => $vorname, 'last_name' => $nachname, 'email' => $email,
            'consent_at' => now(), 'consent_text_version' => 'v1', 'confirmed_at' => now(), 'purge_at' => $this->event->purgeAt(),
        ]);
    }

    private function leitung(): void
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());
    }

    private function teilnehmendeLogin(string $email = 'eltern@example.org'): void
    {
        $this->post('/meine-anmeldungen/code', ['email' => $email])->assertRedirect('/meine-anmeldungen');
        $code = null;
        Mail::assertSent(ParticipantCodeMail::class, function (ParticipantCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->post('/meine-anmeldungen/anmelden', ['code' => $code])->assertRedirect('/meine-anmeldungen');
    }

    #[TestDox('Prüfcode: Prüfsumme über Veranstaltung und Namen, Prüfziffer erkennt Tippfehler')]
    public function test_code(): void
    {
        $service = app(Teilnahmebescheinigung::class);
        $code = $service->codeFor($this->event->id, 'Mia Müller-Lüdenscheidt');

        $this->assertMatchesRegularExpression('/^TB-[0-9A-Z]+-[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]$/', $code);
        $this->assertSame($code, $service->codeFor($this->event->id, '  mia  mueller lüdenscheidt '), 'Schreibweise egal');
        $this->assertTrue($this->event->is($service->verify(strtolower(str_replace('-', ' ', $code)), 'Mia Mueller-Luedenscheidt')));
        $this->assertNull($service->verify($code, 'Max Muster'), 'Code gilt nur mit dem Namen');

        // Ein Zeichen der Signatur ändern (vor „-P“), Länge bleibt gleich.
        $falsch = substr($code, 0, -3).($code[-3] === 'A' ? 'B' : 'A').substr($code, -2);
        $this->assertSame('tippfehler', $service->verify($falsch, 'Mia Müller-Lüdenscheidt'));

        $anderes = Event::query()->create(['title' => 'Anderes', 'slug' => 'anderes', 'status' => 'open', 'starts_at' => now(), 'certificate_enabled' => true]);
        $this->assertNotSame($code, $service->codeFor($anderes->id, 'Mia Müller-Lüdenscheidt'));

        $this->event->update(['certificate_enabled' => false]);
        $this->assertNull($service->verify($code, 'Mia Müller-Lüdenscheidt'), 'Ausgeschaltet: nichts bestätigen');
    }

    #[TestDox('Massenaktion: nur bestätigte Anmeldungen werden freigegeben, mit Mail und Protokoll; Zurücknehmen geht auch')]
    public function test_bulk_approval(): void
    {
        $lea = $this->anmeldung('Lea', 'Klein');
        $ben = $this->anmeldung('Ben', 'Berg', email: 'ben@example.org');
        $warte = $this->anmeldung('Tom', 'Wart', 'waitlist', 'tom@example.org');
        $this->leitung();

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->assertSee('offen')
            ->callTableBulkAction('approveCertificates', [$lea, $ben, $warte], data: ['notify' => true])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified('2 Bescheinigungen freigegeben, Personen werden per E-Mail informiert.');

        $this->assertNotNull($lea->fresh()->certificate_approved_at);
        $this->assertSame(auth()->id(), $ben->fresh()->certificate_approved_by);
        $this->assertNull($warte->fresh()->certificate_approved_at, 'Warteliste wird übersprungen');
        Mail::assertQueued(TeilnahmebescheinigungMail::class, 2);
        $this->assertSame(2, AuditLog::query()->where('action', 'registration.certificate.approve')->count());

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->callTableBulkAction('revokeCertificates', [$ben])
            ->assertHasNoTableBulkActionErrors();
        $this->assertNull($ben->fresh()->certificate_approved_at);
        $this->assertNotNull($lea->fresh()->certificate_approved_at);
    }

    #[TestDox('Einzeln freigeben; ohne eingeschaltete Bescheinigung gibt es keine Aktionen')]
    public function test_single_approval_and_disabled_event(): void
    {
        $mia = $this->anmeldung('Mia', 'Muster');
        $this->leitung();

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->callTableAction('approveCertificate', $mia, data: ['notify' => false])
            ->assertHasNoTableActionErrors();
        $this->assertNotNull($mia->fresh()->certificate_approved_at);
        Mail::assertNothingQueued();

        $this->event->update(['certificate_enabled' => false]);
        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event->fresh(), 'pageClass' => EditEvent::class])
            ->assertTableActionHidden('approveCertificate', $mia)
            ->assertTableBulkActionHidden('approveCertificates');
    }

    #[TestDox('Teilnehmende laden die Bescheinigung erst nach Freigabe; eine hochgeladene hat Vorrang')]
    public function test_participant_download(): void
    {
        $mia = $this->anmeldung('Mia', 'Muster');

        $this->teilnehmendeLogin();
        $this->get('/meine-anmeldungen')->assertOk()->assertDontSee('Teilnahmebescheinigung (PDF)');
        $this->get("/meine-anmeldungen/{$mia->id}/bescheinigung")->assertNotFound();

        $mia->forceFill(['certificate_approved_at' => now()])->save();
        $this->get('/meine-anmeldungen')->assertSee('Teilnahmebescheinigung (PDF)');
        $response = $this->get("/meine-anmeldungen/{$mia->id}/bescheinigung")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));

        Storage::disk('local')->put('anmeldungen/x.pdf', '%PDF-1.4 hochgeladen');
        $hochgeladen = $mia->documents()->create(['type' => 'bescheinigung', 'path' => 'anmeldungen/x.pdf', 'original_name' => 'x.pdf', 'size' => 20]);
        $this->get("/meine-anmeldungen/{$mia->id}/bescheinigung")->assertRedirect("/meine-anmeldungen/dokument/{$hochgeladen->id}");

        $this->post('/meine-anmeldungen/abmelden');
        Mail::fake();
        $this->anmeldung('Ole', 'Anders', email: 'andere@example.org');
        $this->teilnehmendeLogin('andere@example.org');
        $this->get("/meine-anmeldungen/{$mia->id}/bescheinigung")->assertNotFound();
    }

    #[TestDox('Echtheitsprüfung auf der Website: echt, Tippfehler, unbekannt')]
    public function test_verification_page(): void
    {
        $code = app(Teilnahmebescheinigung::class)->codeFor($this->event->id, 'Mia Muster');

        $this->get('/bescheinigung-pruefen?code='.$code)->assertOk()->assertSee($code);
        $this->post('/bescheinigung-pruefen', ['code' => $code, 'name' => 'mia muster'])
            ->assertOk()->assertSee('Die Bescheinigung ist echt.')->assertSee('Woodbadge-Kurs Modul 1b');
        $this->post('/bescheinigung-pruefen', ['code' => $code, 'name' => 'Max Muster'])
            ->assertOk()->assertSee('kennen wir nicht')->assertDontSee('Woodbadge-Kurs Modul 1b');
        $this->post('/bescheinigung-pruefen', ['code' => substr($code, 0, -1).($code[-1] === 'A' ? 'B' : 'A'), 'name' => 'Mia Muster'])
            ->assertOk()->assertSee('Tippfehler');
    }

    #[TestDox('PDF enthält Prüfcode und Name; Vorschau für die Leitung')]
    public function test_pdf(): void
    {
        $mia = $this->anmeldung('Mia', 'Muster');
        $pdf = app(Teilnahmebescheinigung::class)->pdf($mia);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(10_000, strlen($pdf));

        $this->leitung();
        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->callTableAction('previewCertificate', $mia)
            ->assertFileDownloaded('Teilnahmebescheinigung – Woodbadge-Kurs Modul 1b.pdf');
        $this->assertNull($mia->fresh()->certificate_approved_at, 'Vorschau gibt nichts frei');
    }
}
