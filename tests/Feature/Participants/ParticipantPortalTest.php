<?php

namespace Tests\Feature\Participants;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Mail\EventRegistrationMail;
use App\Mail\ParticipantCodeMail;
use App\Mail\RegistrationDocumentMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\RegistrationDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * „Meine Anmeldungen“ (Meilenstein Talia): Übersicht für Teilnehmende ohne
 * Konto – mit E-Mail und Einmalcode –, Stornieren und Dokumente.
 */
class ParticipantPortalTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->event = $this->event('Wölflingstag', now()->addMonth());
    }

    private function event(string $title, $start): Event
    {
        return Event::query()->create([
            'title' => $title, 'slug' => str($title)->slug()->value(), 'status' => 'open', 'location' => 'Trier',
            'starts_at' => $start->copy()->setTime(10, 0), 'ends_at' => $start->copy()->setTime(17, 0),
        ]);
    }

    private function registration(Event $event, array $attrs = []): EventRegistration
    {
        return $event->registrations()->create([
            'status' => 'confirmed', 'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'eltern@example.org',
            'consent_at' => now(), 'consent_text_version' => 'v1', 'confirmed_at' => now(), 'purge_at' => $event->purgeAt(), ...$attrs,
        ]);
    }

    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(ParticipantCodeMail::class, function (ParticipantCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    private function login(string $email = 'eltern@example.org'): void
    {
        $this->post('/meine-anmeldungen/code', ['email' => $email])->assertRedirect('/meine-anmeldungen');
        $this->post('/meine-anmeldungen/anmelden', ['code' => $this->sentCode()])->assertRedirect('/meine-anmeldungen');
    }

    #[TestDox('Mit E-Mail und Einmalcode: Übersicht mit bevorstehenden und vergangenen Anmeldungen, auch für mehrere Personen')]
    public function test_login_with_code_shows_registrations(): void
    {
        $this->registration($this->event);
        $this->registration($this->event, ['first_name' => 'Ben']);
        $this->registration($this->event('Pfingstlager', now()->subMonth()));
        $this->registration($this->event, ['email' => 'andere@example.org', 'first_name' => 'Fremd']);

        $this->get('/meine-anmeldungen')->assertOk()->assertSee('Code schicken')->assertDontSee('Wölflingstag');

        $this->post('/meine-anmeldungen/code', ['email' => ' Eltern@Example.org '])->assertRedirect('/meine-anmeldungen');
        Mail::assertSent(ParticipantCodeMail::class, fn ($mail) => $mail->hasTo('eltern@example.org'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code = $this->sentCode());
        $this->get('/meine-anmeldungen')->assertSee('Code aus der E-Mail')->assertSee('eltern@example.org');

        $this->post('/meine-anmeldungen/anmelden', ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->post('/meine-anmeldungen/anmelden', ['code' => substr($code, 0, 3).' '.substr($code, 3)])->assertRedirect('/meine-anmeldungen');

        $this->get('/meine-anmeldungen')->assertOk()
            ->assertSeeInOrder(['Bevorstehend', 'Wölflingstag', 'Vergangen', 'Pfingstlager'])
            ->assertSee('Mia Muster')->assertSee('Ben Muster')
            ->assertDontSee('Fremd');

        // Der Code gilt nur einmal.
        $this->post('/meine-anmeldungen/abmelden')->assertRedirect();
        $this->post('/meine-anmeldungen/code', ['email' => 'eltern@example.org']);
        $this->post('/meine-anmeldungen/anmelden', ['code' => $code])->assertSessionHasErrors('code');
    }

    #[TestDox('„Meine Anmeldungen“ steht im Hauptmenü als Unterpunkt von „Veranstaltungen“ (Desktop und mobil)')]
    public function test_menu_entry_below_events(): void
    {
        $html = $this->get('/kalender')->assertOk()->getContent();

        $desktop = substr($html, strpos($html, 'aria-label="Hauptnavigation"'));
        $this->assertMatchesRegularExpression('#>Veranstaltungen</a>.*id="subnav-veranstaltungen".*Übersicht Veranstaltungen.*>Meine Anmeldungen</a>.*>Kalender</a>#s', $desktop);

        $mobile = substr($html, strpos($html, 'id="mobile-nav"'), 6000);
        $this->assertMatchesRegularExpression('#>Veranstaltungen</a>\s*<ul[^>]*>\s*<li><a href="[^"]*/meine-anmeldungen"#', $mobile);

        $this->assertMatchesRegularExpression('#aria-current="page"\s*>Meine Anmeldungen#', $this->get('/meine-anmeldungen')->assertOk()->getContent());
    }

    #[TestDox('Unbekannte Adresse: gleiche Antwort, aber keine Mail – so verrät die Seite nicht, wer angemeldet ist')]
    public function test_unknown_email_gets_no_code(): void
    {
        $this->post('/meine-anmeldungen/code', ['email' => 'niemand@example.org'])->assertRedirect('/meine-anmeldungen');
        $this->get('/meine-anmeldungen')->assertSee('Wenn es unter');
        Mail::assertNothingSent();
    }

    #[TestDox('Nach fünf Fehlversuchen ist der Code gesperrt; die Freischaltung endet nach einer Stunde')]
    public function test_attempts_and_session_expiry(): void
    {
        $this->registration($this->event);
        $this->post('/meine-anmeldungen/code', ['email' => 'eltern@example.org']);
        $code = $this->sentCode();
        $wrong = $code === '999999' ? '888888' : '999999';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/meine-anmeldungen/anmelden', ['code' => $wrong]);
        }
        $this->post('/meine-anmeldungen/anmelden', ['code' => $code])->assertSessionHasErrors('code');

        // Neuer Code erst nach einer Minute; dann klappt es.
        $this->travel(2)->minutes();
        Mail::fake();
        $this->login();
        $this->get('/meine-anmeldungen')->assertSee('Wölflingstag');

        $this->travel(61)->minutes();
        $this->get('/meine-anmeldungen')->assertSee('Code schicken')->assertDontSee('Wölflingstag');
    }

    #[TestDox('Eigene Anmeldung stornieren; fremde Anmeldungen und Dokumente sind nicht erreichbar')]
    public function test_cancel_and_access_control(): void
    {
        $mine = $this->registration($this->event);
        $other = $this->registration($this->event, ['email' => 'andere@example.org']);

        $this->post("/meine-anmeldungen/{$mine->id}/stornieren")->assertForbidden();

        $this->login();
        $this->post("/meine-anmeldungen/{$other->id}/stornieren")->assertNotFound();
        $this->assertSame('confirmed', $other->fresh()->status);

        $this->post("/meine-anmeldungen/{$mine->id}/stornieren")->assertRedirect('/meine-anmeldungen');
        $this->assertSame('cancelled', $mine->fresh()->status);
        Mail::assertQueued(EventRegistrationMail::class, fn ($m) => $m->kind === 'cancelled');
        $this->get('/meine-anmeldungen')->assertSee('ist storniert')->assertSee('Storniert');
    }

    #[TestDox('Diözesanbüro lädt Rechnung hoch; die Person wird informiert und lädt sie herunter – andere nicht')]
    public function test_document_upload_and_download(): void
    {
        $mine = $this->registration($this->event);
        $other = $this->registration($this->event, ['email' => 'andere@example.org']);

        Filament::setCurrentPanel('admin');
        $buero = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $buero->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($buero);

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->callTableAction('uploadDocument', $mine, [
                'type' => 'rechnung', 'title' => 'Rechnung 2026-117',
                'path' => UploadedFile::fake()->create('rechnung.pdf', 50, 'application/pdf'),
                'replace' => true, 'notify' => true,
            ])
            ->assertHasNoTableActionErrors();

        $document = RegistrationDocument::query()->sole();
        $this->assertSame($mine->id, $document->event_registration_id);
        Storage::disk('local')->assertExists($document->path);
        Mail::assertQueued(RegistrationDocumentMail::class, fn ($m) => $m->hasTo('eltern@example.org'));

        auth()->logout();
        $this->get("/meine-anmeldungen/dokument/{$document->id}")->assertForbidden();

        $this->login('andere@example.org');
        $this->get("/meine-anmeldungen/dokument/{$document->id}")->assertNotFound();
        $this->post('/meine-anmeldungen/abmelden');

        Mail::fake();
        $this->login();
        $this->get('/meine-anmeldungen')->assertSee('Rechnung 2026-117 (PDF)');
        $response = $this->get("/meine-anmeldungen/dokument/{$document->id}")->assertOk();
        $this->assertStringContainsString(rawurlencode('Rechnung 2026-117 – Wölflingstag.pdf'), (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $this->assertNotNull($other);
    }

    #[TestDox('Neue Bescheinigung ersetzt die alte; Dateien verschwinden mit der Anmeldung (Löschfrist)')]
    public function test_replace_and_purge_documents(): void
    {
        $registration = $this->registration($this->event);
        $make = function (string $type) use ($registration) {
            $path = 'anmeldungen/'.$this->event->id.'/'.$type.uniqid().'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4');

            return $registration->documents()->create(['type' => $type, 'path' => $path, 'original_name' => 'x.pdf']);
        };
        $old = $make('bescheinigung');
        $rechnung = $make('rechnung');

        Filament::setCurrentPanel('admin');
        $buero = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $buero->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($buero);
        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->callTableAction('uploadDocument', $registration, [
                'type' => 'bescheinigung', 'path' => UploadedFile::fake()->create('neu.pdf', 20, 'application/pdf'), 'replace' => true, 'notify' => false,
            ]);

        Storage::disk('local')->assertMissing($old->path);
        $this->assertSame(['bescheinigung', 'rechnung'], $registration->documents()->pluck('type')->sort()->values()->all());
        Mail::assertNotQueued(RegistrationDocumentMail::class);

        $paths = $registration->documents()->pluck('path')->all();
        $registration->update(['purge_at' => now()->subDay()]);
        $this->artisan('registrations:purge')->assertSuccessful();

        $this->assertSame(0, RegistrationDocument::query()->count());
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->assertNotNull($rechnung);
    }
}
