<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Mail\EventRegistrationMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Gruppierung;
use App\Models\User;
use App\Services\EventRegistrations;
use App\Services\ParticipantExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Veranstaltungsmanagement, Pflichtenheft 6.7, 9.3 und Abnahmekriterien.
 */
class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function event(array $attrs = []): Event
    {
        return Event::query()->create([
            'title' => 'Wölflingstag',
            'slug' => 'woelflingstag',
            'starts_at' => now()->addMonths(2)->setTime(10, 0),
            'ends_at' => now()->addMonths(2)->setTime(17, 0),
            'location' => 'Trier',
            'stage' => 'woelflinge',
            'status' => 'open',
            'form_fields' => ['birth_year' => 'required', 'stamm' => 'optional', 'diet' => 'optional'],
            ...$attrs,
        ]);
    }

    private function user(string $email, string $role, string $scopeType = 'global', ?string $scopeKey = null): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);

        return $user->fresh();
    }

    private function register(Event $event, array $input = []): TestResponse
    {
        return $this->post("/veranstaltungen/{$event->slug}/anmeldung", [
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'email' => 'Eltern@Example.org',
            'birth_year' => now()->year - 20,
            'stamm' => 'Stamm Sankt Georg',
            'diet' => 'vegetarisch',
            'consent' => '1',
            ...$input,
        ]);
    }

    private function sentToken(string $kind): string
    {
        $token = null;
        Mail::assertQueued(EventRegistrationMail::class, function (EventRegistrationMail $mail) use ($kind, &$token) {
            if ($mail->kind === $kind) {
                $token = $mail->token;
            }

            return $mail->kind === $kind;
        });

        return $token;
    }

    #[TestDox('Baukasten-Felder Funktion/Gremium und Übernachtung (z. B. Diözesanversammlung)')]
    public function test_gremium_and_overnight_fields(): void
    {
        $event = $this->event(['form_fields' => ['gremium' => 'required', 'uebernachtung' => 'required']]);

        $this->get('/veranstaltungen/woelflingstag')->assertOk()
            ->assertSeeInOrder(['Funktion / Gremium', 'Übernachtung *', 'Ganze Zeit mit Übernachtung']);

        $this->register($event, ['gremium' => '', 'uebernachtung' => 'irgendwann'])->assertSessionHasErrors(['gremium', 'uebernachtung']);
        $this->register($event, ['gremium' => 'Delegierte Bezirk Saar-Hochwald', 'uebernachtung' => 'komplett'])->assertSessionHasNoErrors();

        $this->assertSame(['gremium' => 'Delegierte Bezirk Saar-Hochwald', 'uebernachtung' => 'komplett'], EventRegistration::query()->sole()->data);
    }

    #[TestDox('Anwesenheit nach Tagen: ganze Zeit oder einzelne Tage, Zählung je Tag und Spalten im Export')]
    public function test_attendance_days(): void
    {
        $event = $this->event([
            'starts_at' => '2026-11-20 17:00:00', 'ends_at' => '2026-11-22 13:30:00',
            'form_fields' => ['tage' => 'required'],
        ]);

        $this->get('/veranstaltungen/woelflingstag')->assertOk()
            ->assertSee('Anwesenheit (Tage) *')
            ->assertSee('Die ganze Zeit (Fr, 20.11. bis So, 22.11.)')
            ->assertSeeInOrder(['Nur an einzelnen Tagen', 'Fr, 20.11.', 'Sa, 21.11.', 'So, 22.11.']);

        $this->register($event, ['email' => 'a@example.org'])->assertSessionHasErrors('tage_modus');
        $this->register($event, ['email' => 'a@example.org', 'tage_modus' => 'teilweise'])->assertSessionHasErrors('tage');
        $this->register($event, ['email' => 'a@example.org', 'tage_modus' => 'teilweise', 'tage' => ['2026-11-25']])->assertSessionHasErrors('tage.0');

        $this->register($event, ['email' => 'a@example.org', 'tage_modus' => 'alle'])->assertSessionHasNoErrors();
        $this->register($event, ['email' => 'b@example.org', 'first_name' => 'Ben', 'tage_modus' => 'teilweise', 'tage' => ['2026-11-21', '2026-11-20']])->assertSessionHasNoErrors();

        $registrations = EventRegistration::query()->orderBy('id')->get();
        $this->assertSame(['2026-11-20', '2026-11-21', '2026-11-22'], $registrations[0]->data['tage']);
        $this->assertSame(['2026-11-20', '2026-11-21'], $registrations[1]->data['tage']);
        $this->assertSame('ganze Zeit', $event->fieldValueLabel($event->formFields()['tage'], $registrations[0]->data['tage']));
        $this->assertSame('Fr, 20.11. · Sa, 21.11.', $event->fieldValueLabel($event->formFields()['tage'], $registrations[1]->data['tage']));

        $registrations->each(fn ($r) => $r->update(['status' => 'confirmed', 'confirmed_at' => now()]));
        $export = app(ParticipantExport::class);
        $headings = $export->headings($event);
        $this->assertSame(['Anwesenheit (Tage)', 'Fr, 20.11.', 'Sa, 21.11.', 'So, 22.11.'], array_slice($headings, 5, 4));
        $rows = $export->rows($event);
        $this->assertSame(['ganze Zeit', 'x', 'x', 'x'], array_slice($rows[0], 5, 4));
        $this->assertSame(['Fr, 20.11. · Sa, 21.11.', 'x', 'x', ''], array_slice($rows[1], 5, 4));

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->user('buero@dpsg-trier.de', 'verwaltung'));
        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->assertSee('Anwesend (bestätigt): Fr, 20.11.: 2 · Sa, 21.11.: 2 · So, 22.11.: 1')
            ->assertSee('Fr, 20.11. · Sa, 21.11.');
    }

    #[TestDox('Bei eintägigen Veranstaltungen erscheint die Tage-Abfrage nicht')]
    public function test_days_field_hidden_for_single_day_events(): void
    {
        $event = $this->event(['form_fields' => ['tage' => 'required', 'birth_year' => 'optional']]);

        $this->assertArrayNotHasKey('tage', $event->formFields());
        $this->get('/veranstaltungen/woelflingstag')->assertOk()->assertDontSee('Anwesenheit (Tage)');
        $this->register($event)->assertSessionHasNoErrors();
    }

    #[TestDox('Feld „Stamm oder Bezirk“ schlägt aktive Stämme und Bezirke von der Karte vor')]
    public function test_stamm_field_suggests_staemme_and_bezirke(): void
    {
        $bezirk = Gruppierung::query()->create(['typ' => 'bezirk', 'name' => 'Testbezirk', 'slug' => 'testbezirk', 'active' => true]);
        Gruppierung::query()->create(['typ' => 'stamm', 'name' => 'Testdorf, St. Konrad', 'slug' => 'test-st-konrad', 'ort' => 'Testdorf', 'parent_id' => $bezirk->id, 'active' => true]);
        Gruppierung::query()->create(['typ' => 'stamm', 'name' => 'Aufgelöst', 'slug' => 'test-aufgeloest', 'active' => false]);
        $this->event(['form_fields' => ['stamm' => 'required']]);

        $this->get('/veranstaltungen/woelflingstag')->assertOk()
            ->assertSee('Stamm oder Bezirk *')
            ->assertSee('<option value="Bezirk Testbezirk">Bezirk</option>', false)
            ->assertSee('<option value="Testdorf, St. Konrad">Stamm · Bezirk Testbezirk</option>', false)
            ->assertSee('stammAutocomplete(', false)
            ->assertDontSee('Aufgelöst');
    }

    #[TestDox('Anmeldung mit Bestätigungsmail; Zusatzangaben werden verschlüsselt gespeichert')]
    public function test_double_opt_in_flow_with_encrypted_extra_data(): void
    {
        $event = $this->event();

        $this->get('/veranstaltungen')->assertOk()->assertSee('Wölflingstag');
        $this->get('/veranstaltungen/woelflingstag')->assertOk()->assertSee('Geburtsjahr *')->assertSee('Verpflegung')->assertDontSee('Telefonnummer');

        $this->register($event)->assertRedirect('/veranstaltungen/woelflingstag#anmeldung');

        $registration = EventRegistration::query()->sole();
        $this->assertSame('pending', $registration->status);
        $this->assertSame('eltern@example.org', $registration->email);
        $this->assertSame(['stamm' => 'Stamm Sankt Georg', 'diet' => 'vegetarisch'], $registration->data);
        $this->assertSame(config('dpsg.event_consent.version'), $registration->consent_text_version);
        // Zusatzangaben verschlüsselt, Token nur als Hash.
        $raw = DB::table('event_registrations')->first();
        $this->assertStringNotContainsString('Sankt Georg', $raw->data);
        $token = $this->sentToken('doi');
        $this->assertNotSame($token, $raw->confirm_token_hash);

        // Ein Klick in der Mail reicht: Der Browser sendet das Formular selbst ab
        // (GET allein ändert nichts – Linkscanner bestätigen also nichts).
        $this->get("/anmeldung/bestaetigen/{$token}")->assertOk()->assertSee('Ja, Anmeldung bestätigen')
            ->assertSee('x-init="$nextTick(() => $el.submit())"', false);
        $this->assertSame('pending', $registration->fresh()->status);
        $this->post("/anmeldung/bestaetigen/{$token}")->assertOk()->assertSee('Anmeldung bestätigt');

        $registration->refresh();
        $this->assertSame('confirmed', $registration->status);
        $this->assertNull($registration->confirm_token_hash);
        $this->assertSame($event->purgeAt()->format('Y-m-d H:i:s'), $registration->purge_at->format('Y-m-d H:i:s'));
        $this->post("/anmeldung/bestaetigen/{$token}")->assertSee('Link nicht mehr gültig');

        // Stornieren über den Link aus der Bestätigungsmail.
        $cancelToken = $this->sentToken('confirmed');
        $this->get("/anmeldung/stornieren/{$cancelToken}")->assertOk()->assertSee('Mia Muster')->assertDontSee('$el.submit()', false);
        $this->post("/anmeldung/stornieren/{$cancelToken}")->assertOk()->assertSee('Anmeldung storniert');
        $this->assertSame('cancelled', $registration->fresh()->status);
        Mail::assertQueued(EventRegistrationMail::class, fn ($m) => $m->kind === 'cancelled');
    }

    #[TestDox('Warteliste mit automatischem Nachrücken')]
    public function test_waitlist_and_automatic_promotion(): void
    {
        $event = $this->event(['capacity' => 1, 'waitlist_enabled' => true]);

        $this->register($event);
        $this->post('/anmeldung/bestaetigen/'.$this->sentToken('doi'));
        $first = EventRegistration::query()->sole();

        Mail::fake();
        $this->get('/veranstaltungen/woelflingstag')->assertSee('Auf die Warteliste');
        $this->register($event, ['first_name' => 'Ben']);
        $this->post('/anmeldung/bestaetigen/'.$this->sentToken('doi'))->assertSee('Warteliste');
        $second = EventRegistration::query()->where('first_name', 'Ben')->sole();
        $this->assertSame('waitlist', $second->status);

        app(EventRegistrations::class)->cancel($first);

        $this->assertSame('confirmed', $second->fresh()->status);
        Mail::assertQueued(EventRegistrationMail::class, fn ($m) => $m->kind === 'promoted' && $m->registration->is($second) && $m->token);
    }

    #[TestDox('Volle Veranstaltung ohne Warteliste nimmt keine Anmeldungen mehr an')]
    public function test_full_event_without_waitlist_rejects_further_registrations(): void
    {
        $event = $this->event(['capacity' => 1]);

        // Zwei unbestätigte Anmeldungen; nur die erste Bestätigung bekommt den Platz.
        $this->register($event);
        $this->register($event, ['first_name' => 'Ben']);
        $tokens = [];
        Mail::assertQueued(EventRegistrationMail::class, function ($m) use (&$tokens) {
            $tokens[] = $m->token;

            return true;
        });

        $this->post("/anmeldung/bestaetigen/{$tokens[0]}")->assertSee('Anmeldung bestätigt');
        $this->post("/anmeldung/bestaetigen/{$tokens[1]}")->assertSee('Leider ausgebucht');
        $this->assertSame(1, EventRegistration::query()->count());

        $this->get('/veranstaltungen/woelflingstag')->assertSee('Leider sind alle Plätze vergeben')->assertDontSee('Verbindlich anmelden');
        $this->register($event, ['first_name' => 'Cleo']);
        $this->assertSame(1, EventRegistration::query()->count());
    }

    #[TestDox('Minderjährige brauchen die Einwilligung der Sorgeberechtigten')]
    public function test_minors_need_guardian_consent(): void
    {
        $event = $this->event();
        $child = now()->addMonths(2)->year - 10;

        $this->register($event, ['birth_year' => $child])->assertSessionHasErrors('by_guardian')->assertSessionMissing('anmeldung');
        $this->register($event, ['birth_year' => $child, 'by_guardian' => '1'])->assertSessionHasErrors(['guardian_name', 'guardian_consent']);
        $this->register($event, ['birth_year' => $child, 'by_guardian' => '1', 'guardian_name' => 'Eva Muster', 'guardian_consent' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('anmeldung', 'angefragt');

        $registration = EventRegistration::query()->sole();
        $this->assertSame('Eva Muster', $registration->guardian_name);
        $this->assertTrue($registration->guardian_consent);
    }

    #[TestDox('Eingaben werden geprüft, Spam-Bots abgewiesen, geschlossene Anmeldungen gesperrt')]
    public function test_validation_honeypot_and_unavailable_events(): void
    {
        $event = $this->event();

        $this->register($event, ['consent' => null, 'birth_year' => null])->assertSessionHasErrors(['consent', 'birth_year']);
        $this->register($event, ['website' => 'spam'])->assertRedirect();
        $this->register($event, ['diet' => 'nur-schokolade'])->assertSessionHasErrors('diet');
        $this->assertSame(0, EventRegistration::query()->count());

        $this->event(['slug' => 'entwurf', 'status' => 'draft']);
        $this->get('/veranstaltungen/entwurf')->assertNotFound();

        $closed = $this->event(['slug' => 'vorbei', 'reg_closes_at' => now()->subDay()]);
        $this->get('/veranstaltungen/vorbei')->assertSee('Die Anmeldung ist geschlossen');
        $this->register($closed);
        $this->assertSame(0, EventRegistration::query()->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    #[TestDox('Unbestätigte und abgelaufene Anmeldungen werden gelöscht')]
    public function test_unconfirmed_and_expired_registrations_are_purged(): void
    {
        $event = $this->event();
        $this->register($event);
        $token = $this->sentToken('doi');

        $this->travel(49)->hours();
        $this->post("/anmeldung/bestaetigen/{$token}")->assertSee('Link abgelaufen');
        $this->assertSame(0, EventRegistration::query()->count());

        $this->register($event);
        $this->travel(49)->hours();
        $this->artisan('registrations:purge --unconfirmed')->assertSuccessful();
        $this->assertSame(0, EventRegistration::query()->count());

        $confirmed = $event->registrations()->create([
            'status' => 'confirmed', 'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.org',
            'consent_text_version' => 'x', 'consent_at' => now(), 'purge_at' => $event->purgeAt(),
        ]);
        $this->artisan('registrations:purge')->assertSuccessful();
        $this->assertNotNull($confirmed->fresh());

        $this->travelTo($event->purgeAt()->addMinute());
        $this->artisan('registrations:purge')->assertSuccessful();
        $this->assertNull($confirmed->fresh());
    }

    #[TestDox('Teilnehmendenlisten nur für Berechtigte; Export wird protokolliert')]
    public function test_participant_access_follows_scope_and_export_is_audited_and_masked(): void
    {
        Filament::setCurrentPanel('admin');
        $woelflinge = $this->event();
        $rover = $this->event(['slug' => 'rover-hike', 'title' => 'Rover-Hike', 'stage' => 'rover']);
        $registration = $woelflinge->registrations()->create([
            'status' => 'confirmed', 'first_name' => '=HYPERLINK("x")', 'last_name' => 'Muster', 'email' => 'mia@example.org',
            'birth_year' => 2015, 'data' => ['stamm' => 'Stamm Sankt Georg', 'diet' => 'vegan'],
            'consent_text_version' => 'x', 'consent_at' => now(), 'confirmed_at' => now(),
        ]);

        $stufenadmin = $this->user('woe@dpsg-trier.de', 'stufenadmin', 'stage', 'woelflinge');
        $vorstand = $this->user('vorstand@dpsg-trier.de', 'vorstand');
        $autor = $this->user('autor@dpsg-trier.de', 'autor', 'own');

        $this->assertTrue($stufenadmin->can('manageParticipants', $woelflinge));
        $this->assertFalse($stufenadmin->can('manageParticipants', $rover));
        $this->assertFalse($vorstand->can('manageParticipants', $woelflinge));
        $this->actingAs($autor)->get('/admin/events')->assertForbidden();

        $this->actingAs($stufenadmin);
        Livewire::test(ListEvents::class)->assertCanSeeTableRecords([$woelflinge])->assertCanNotSeeTableRecords([$rover]);
        $this->get("/admin/events/{$rover->id}/edit")->assertNotFound();
        $this->assertTrue(RegistrationsRelationManager::canViewForRecord($woelflinge, EditEvent::class));

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $woelflinge, 'pageClass' => EditEvent::class])
            ->assertCanSeeTableRecords([$registration])
            ->callTableAction('export')
            ->assertFileDownloaded('Teilnehmende-woelflingstag-'.now()->format('Y-m-d').'.xlsx');
        $this->assertSame(1, AuditLog::query()->where('action', 'event.export')->where('subject_id', $woelflinge->id)->where('user_id', $stufenadmin->id)->count());

        // Vorstand: Anzahlen ja, Teilnehmerdaten nein.
        $this->actingAs($vorstand);
        Livewire::test(ListEvents::class)->assertCanSeeTableRecords([$woelflinge, $rover]);
        $this->assertFalse(RegistrationsRelationManager::canViewForRecord($woelflinge, EditEvent::class));

        $export = app(ParticipantExport::class);
        $this->assertSame(['Nr.', 'Status', 'Vorname', 'Nachname', 'E-Mail', 'Geburtsjahr', 'Stamm oder Bezirk', 'Verpflegung', 'Angemeldet durch (sorgeberechtigt)', 'Bestätigt am'], $export->headings($woelflinge));
        $row = $export->rows($woelflinge)[0];
        $this->assertSame("'=HYPERLINK(\"x\")", $row[2]);
        $this->assertSame('Vegan', $row[7]);
        $this->assertSame("'+49 651", ParticipantExport::mask('+49 651'));
        $this->assertSame("'@SUMME(A1)", ParticipantExport::mask('@SUMME(A1)'));
        $this->assertSame('Muster', ParticipantExport::mask('Muster'));
    }

    #[TestDox('Stufenadmins legen Veranstaltungen nur für die eigene Stufe an')]
    public function test_stufenadmin_creates_event_only_for_own_stage(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->user('woe@dpsg-trier.de', 'stufenadmin', 'stage', 'woelflinge'));

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Pfadi-Lager', 'slug' => 'pfadi-lager', 'starts_at' => now()->addMonth(), 'stage' => 'pfadi', 'status' => 'open', 'retention_days' => 90])
            ->call('create')
            ->assertHasFormErrors(['stage']);

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Wölflingstag', 'slug' => 'woelflingstag', 'starts_at' => now()->addMonth(), 'stage' => 'woelflinge', 'status' => 'open', 'retention_days' => 60, 'form_fields' => ['birth_year' => 'required', 'phone' => 'optional']])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->sole();
        $this->assertSame(['birth_year', 'phone'], array_keys($event->formFields()));
        $this->assertTrue($event->formFields()['birth_year']['required']);
        $this->assertSame(60, $event->retention_days);
    }
}
