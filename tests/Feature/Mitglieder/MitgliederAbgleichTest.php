<?php

namespace Tests\Feature\Mitglieder;

use App\Filament\Pages\NamiZugang;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Jobs\CheckEventMembers;
use App\Mail\EventRegistrationMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Gruppierung;
use App\Models\User;
use App\Services\Mitglieder\HitobitoVerzeichnis;
use App\Services\Mitglieder\Mitglied;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Services\ParticipantExport;
use App\Services\PrivacyRequests;
use App\Support\Health\HealthChecks;
use App\Support\NamiAmpel;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Abgleich der Teilnehmenden mit NaMi 2 bzw. NaMi 3 (hitobito),
 * docs/mitgliederabgleich.md.
 */
class MitgliederAbgleichTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<Mitglied> */
    private array $bestand = [];

    /** @var list<array{0: string, 1: string}> */
    private array $suchen = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mitglieder.driver' => 'nami', 'mitglieder.nami.auth' => 'konto']);

        // Verzeichnis im Speicher: liefert alle Personen mit passendem Nachnamen.
        $this->app->instance(Mitgliederverzeichnis::class, new class($this) implements Mitgliederverzeichnis
        {
            public function __construct(private MitgliederAbgleichTest $test) {}

            public function name(): string
            {
                return 'Test-NaMi';
            }

            public function suche(string $vorname, string $nachname): array
            {
                return $this->test->sucheIm($vorname, $nachname);
            }

            public function pruefe(): void {}
        });
    }

    /** @return list<Mitglied> */
    public function sucheIm(string $vorname, string $nachname): array
    {
        $this->suchen[] = [$vorname, $nachname];

        return array_values(array_filter($this->bestand, fn (Mitglied $m) => mb_strtolower($m->nachname) === mb_strtolower($nachname)));
    }

    private function mitglied(string $nummer, string $vorname, string $nachname, ?string $geburt = '2010-05-01', bool $aktiv = true, ?string $email = null): Mitglied
    {
        return new Mitglied($nummer, $vorname, $nachname, $geburt ? Carbon::parse($geburt) : null, $email, 'Stamm Sankt Georg', $aktiv);
    }

    private function event(array $attrs = []): Event
    {
        return Event::query()->create([
            'title' => 'Wölflingslager', 'slug' => 'woelflingslager', 'status' => 'open',
            'starts_at' => now()->addMonths(2), 'ends_at' => now()->addMonths(2)->addDays(2),
            'form_fields' => ['birth_year' => 'required'], 'member_check' => true,
            ...$attrs,
        ]);
    }

    private function anmeldung(Event $event, string $vorname, string $nachname, ?int $jahr = 2010, string $email = 'eltern@example.org', string $status = 'confirmed'): EventRegistration
    {
        return $event->registrations()->create([
            'status' => $status, 'first_name' => $vorname, 'last_name' => $nachname, 'email' => $email, 'birth_year' => $jahr,
            'consent_text_version' => 'x', 'consent_at' => now(), 'confirmed_at' => now(),
        ]);
    }

    #[TestDox('Abgleich: Mitglied, ehemalig, nicht gefunden, unklar; Umlaute, Doppelnamen, Geburtsjahr und E-Mail')]
    public function test_matching_rules(): void
    {
        $this->bestand = [
            $this->mitglied('111', 'Jonas', 'Müller'),
            $this->mitglied('112', 'Anna Lena', 'Schäfer'),
            $this->mitglied('113', 'Paul', 'Weber', aktiv: false),
            $this->mitglied('114', 'Lea', 'Klein', '2010-02-02', email: 'a@example.org'),
            $this->mitglied('115', 'Lea', 'Klein', '2010-09-09', email: 'b@example.org'),
            $this->mitglied('116', 'Tom', 'Fischer', '2001-01-01'),
        ];
        $event = $this->event();
        $abgleich = app(MitgliederAbgleich::class);

        $jonas = $this->anmeldung($event, 'jonas', 'MÜLLER');
        $this->assertSame('mitglied', $abgleich->pruefe($jonas));
        $this->assertSame(['111', 'Stamm Sankt Georg'], [$jonas->member_number, $jonas->member_group]);

        $this->assertSame('mitglied', $abgleich->pruefe($this->anmeldung($event, 'Anna-Lena', 'Schäfer')));
        $this->assertSame(['Anna', 'Schäfer'], end($this->suchen), 'Gesucht wird nur mit erstem Vornamen und Nachnamen.');
        $this->assertSame('ehemalig', $abgleich->pruefe($this->anmeldung($event, 'Paul', 'Weber')));
        $this->assertSame('nicht_gefunden', $abgleich->pruefe($this->anmeldung($event, 'Tom', 'Fischer')), 'Geburtsjahr passt nicht.');
        $this->assertSame('nicht_gefunden', $abgleich->pruefe($this->anmeldung($event, 'Greta', 'Neu')));

        $unklar = $this->anmeldung($event, 'Lea', 'Klein', 2010, 'c@example.org');
        $this->assertSame('unklar', $abgleich->pruefe($unklar));
        $this->assertNull($unklar->member_number);
        $perMail = $this->anmeldung($event, 'Lea', 'Klein', 2010, 'B@example.org');
        $this->assertSame('mitglied', $abgleich->pruefe($perMail));
        $this->assertSame('115', $perMail->member_number);
    }

    #[TestDox('Mitgliedsnummer nur aus NaMi: einmal zugeordnet, löst sie beim erneuten Abgleich Namensgleichheit auf')]
    public function test_member_number_from_earlier_check(): void
    {
        $this->bestand = [
            $this->mitglied('0012345', 'Mia', 'Muster', '2010-02-02'),
            $this->mitglied('0067890', 'Mia', 'Muster', '2010-09-09'),
        ];
        $event = $this->event(['form_fields' => ['birth_year' => 'optional']]);
        $abgleich = app(MitgliederAbgleich::class);

        $mia = $this->anmeldung($event, 'Mia', 'Muster', 2010);
        $this->assertSame('unklar', $abgleich->pruefe($mia), 'Namensgleichheit ohne bekannte Nummer');

        // z. B. früher eindeutig zugeordnet (vor dem Eintritt der zweiten Mia)
        $mia->forceFill(['member_number' => '12345'])->save();
        $this->assertSame('mitglied', $abgleich->pruefe($mia->fresh()));
        $this->assertSame('0012345', $mia->fresh()->member_number);

        // Bekannte Nummer nicht mehr unter den Treffern: Regeln wie ohne Nummer.
        $mia->forceFill(['member_number' => '999'])->save();
        $this->assertSame('unklar', $abgleich->pruefe($mia->fresh()));
    }

    #[TestDox('Stamm bzw. Bezirk aus NaMi übernehmen, wenn die Person nichts angegeben hat (Zuordnung über die DPSG-Nummer der Gruppierung)')]
    public function test_stamm_from_nami(): void
    {
        $bezirk = Gruppierung::query()->create(['typ' => 'bezirk', 'nummer' => '21-99-00', 'name' => 'Testbezirk', 'slug' => 'testbezirk', 'active' => true]);
        Gruppierung::query()->create(['typ' => 'stamm', 'nummer' => '21-99-08', 'name' => 'Sankt Konrad', 'slug' => 'st-konrad', 'parent_id' => $bezirk->id, 'active' => true]);
        $this->bestand = [
            new Mitglied('4711', 'Mia', 'Muster', null, null, 'Testdorf St. Konrad 219908', true),
            new Mitglied('4712', 'Ben', 'Berg', null, null, 'Irgendwo ganz anders', true),
            new Mitglied('4713', 'Lea', 'Klein', null, null, 'Bezirk Testbezirk 219900', true),
            new Mitglied('4714', 'Kai', 'Kurz', null, null, 'Testdorf St. Konrad 219908', true),
        ];
        $event = $this->event(['form_fields' => ['stamm' => 'optional']]);
        $abgleich = app(MitgliederAbgleich::class);

        $mia = $this->anmeldung($event, 'Mia', 'Muster', null);
        $abgleich->pruefe($mia);
        $this->assertSame(['stamm' => 'Sankt Konrad'], $mia->fresh()->data);

        $ben = $this->anmeldung($event, 'Ben', 'Berg', null, email: 'ben@example.org');
        $abgleich->pruefe($ben);
        $this->assertSame(['stamm' => 'Irgendwo ganz anders'], $ben->fresh()->data, 'unbekannte Gruppierung: Bezeichnung aus NaMi');

        $lea = $this->anmeldung($event, 'Lea', 'Klein', null, email: 'lea@example.org');
        $abgleich->pruefe($lea);
        $this->assertSame(['stamm' => 'Bezirk Testbezirk'], $lea->fresh()->data, 'Mitglied auf Bezirksebene');

        $kai = $this->anmeldung($event, 'Kai', 'Kurz', null, email: 'kai@example.org');
        $kai->update(['data' => ['stamm' => 'Mein Stamm']]);
        $abgleich->pruefe($kai->fresh());
        $this->assertSame(['stamm' => 'Mein Stamm'], $kai->fresh()->data, 'eigene Angabe bleibt');

        $ohneFeld = $this->event(['slug' => 'ohne-stamm', 'form_fields' => []]);
        $tom = $this->anmeldung($ohneFeld, 'Mia', 'Muster', null, email: 'tom@example.org');
        $abgleich->pruefe($tom);
        $this->assertNull($tom->fresh()->data, 'ohne Stamm-Feld wird nichts übernommen');
    }

    #[TestDox('Nur für Mitglieder eines Stamms/Bezirks: Abgleich markiert eigene/andere Gruppierung (DPSG-Nummer, sonst Karte); Hinweis auf der Seite, Filter, Export')]
    public function test_members_of_own_gruppierung(): void
    {
        $bezirk = Gruppierung::query()->create(['typ' => 'bezirk', 'nummer' => '21-99-00', 'name' => 'Testbezirk', 'slug' => 'testbezirk', 'active' => true]);
        $konrad = Gruppierung::query()->create(['typ' => 'stamm', 'nummer' => '21-99-08', 'name' => 'Sankt Konrad', 'slug' => 'st-konrad', 'parent_id' => $bezirk->id, 'active' => true]);
        Gruppierung::query()->create(['typ' => 'stamm', 'name' => 'Ohne Nummer', 'slug' => 'ohne-nummer', 'parent_id' => $bezirk->id, 'active' => true]);
        $this->bestand = [
            new Mitglied('1', 'Mia', 'Muster', null, null, 'Testdorf St. Konrad 219908', true),
            new Mitglied('2', 'Ben', 'Berg', null, null, 'Nachbardorf Sankt Paul 219907', true),
            new Mitglied('3', 'Lea', 'Klein', null, null, 'Ganz woanders 218801', true),
            new Mitglied('4', 'Kai', 'Kurz', null, null, 'Stamm Ohne Nummer', true),
            new Mitglied('5', 'Tom', 'Tal', null, null, 'Irgendwas Unbekanntes', true),
        ];
        $abgleich = app(MitgliederAbgleich::class);

        // Stammesveranstaltung
        $event = $this->event(['member_gruppierung_id' => $konrad->id]);
        $mia = $this->anmeldung($event, 'Mia', 'Muster', null);
        $ben = $this->anmeldung($event, 'Ben', 'Berg', null, email: 'ben@example.org');
        $tom = $this->anmeldung($event, 'Tom', 'Tal', null, email: 'tom@example.org');
        $gast = $this->anmeldung($event, 'Gina', 'Gast', null, email: 'gina@example.org');
        $gast->update(['data' => ['herkunft' => 'gast']]);
        foreach ([$mia, $ben, $tom, $gast] as $r) {
            $abgleich->pruefe($r->fresh());
        }
        $this->assertTrue($mia->fresh()->member_own_group);
        $this->assertFalse($ben->fresh()->member_own_group, 'anderer Stamm im selben Bezirk');
        $this->assertNull($tom->fresh()->member_own_group, 'nicht zuzuordnen');
        $this->assertFalse($gast->fresh()->member_own_group);

        $this->get('/veranstaltungen/woelflingslager')->assertOk()
            ->assertSee('Mitglieder von Stamm Sankt Konrad')
            ->assertSee('Diese Veranstaltung ist für Mitglieder von Stamm Sankt Konrad.');
        $export = app(ParticipantExport::class);
        $this->assertContains('Eigene Gruppierung', $export->headings($event));
        $this->assertSame('ja', collect($export->rows($event))->firstWhere(3, 'Muster')[array_search('Eigene Gruppierung', $export->headings($event), true)]);

        // Bezirksveranstaltung: alle Stämme des Bezirks, auch ohne Nummer über die Karte
        $bezirksEvent = $this->event(['slug' => 'bezirkstag', 'member_gruppierung_id' => $bezirk->id]);
        $ergebnis = [];
        foreach (['Mia' => 'Muster', 'Ben' => 'Berg', 'Lea' => 'Klein', 'Kai' => 'Kurz'] as $vor => $nach) {
            $r = $this->anmeldung($bezirksEvent, $vor, $nach, null, email: strtolower($vor).'@bezirk.example.org');
            $abgleich->pruefe($r);
            $ergebnis[$vor] = $r->fresh()->member_own_group;
        }
        $this->assertSame(['Mia' => true, 'Ben' => true, 'Lea' => false, 'Kai' => true], $ergebnis);
        $this->assertSame('Mitglieder im Bezirk Testbezirk', $bezirksEvent->zielgruppeMitglieder());

        // Ohne Einschränkung: kein Ergebnis; Entscheidung von Hand rechnet neu
        $offen = $this->event(['slug' => 'offen']);
        $r = $this->anmeldung($offen, 'Mia', 'Muster', null, email: 'x@example.org');
        $abgleich->pruefe($r);
        $this->assertNull($r->fresh()->member_own_group);
        MitgliederAbgleich::manuell($ben->fresh(), 'mitglied', '2', 'Testdorf St. Konrad 219908');
        $this->assertTrue($ben->fresh()->member_own_group);
    }

    #[TestDox('Gäste aus anderen Gliederungen/Verbänden werden nicht abgefragt, sondern als „Gast“ markiert')]
    public function test_guests_are_not_looked_up(): void
    {
        $this->bestand = [$this->mitglied('4711', 'Mia', 'Muster', '2010-03-03')];
        $event = $this->event(['form_fields' => ['birth_year' => 'required', 'stamm' => 'optional']]);
        $gast = $this->anmeldung($event, 'Mia', 'Muster', 2010);
        $gast->update(['data' => ['herkunft' => 'gast', 'stamm' => 'DV Köln']]);

        $this->assertSame('gast', app(MitgliederAbgleich::class)->pruefe($gast->fresh()));
        $this->assertSame([], $this->suchen);
        $this->assertSame(['gast', null], [$gast->fresh()->member_status, $gast->fresh()->member_number]);
    }

    #[TestDox('Nach der Bestätigung wird automatisch abgeglichen; Einwilligung mit eigenem Hinweis und eigener Version')]
    public function test_flow_after_confirmation(): void
    {
        $this->bestand = [$this->mitglied('4711', 'Mia', 'Muster', '2010-03-03')];
        $event = $this->event();

        $this->get('/veranstaltungen/woelflingslager')->assertOk()->assertSee('ob du Mitglied bist');
        $this->post('/veranstaltungen/woelflingslager/anmeldung', [
            'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'eltern@example.org',
            'birth_year' => 2010, 'by_guardian' => '1', 'guardian_name' => 'Eva Muster', 'guardian_consent' => '1', 'consent' => '1',
        ])->assertSessionHasNoErrors();

        $registration = EventRegistration::query()->sole();
        $this->assertSame(config('dpsg.event_consent.version').'+mitglieder-'.config('mitglieder.consent.version'), $registration->consent_text_version);
        $this->assertNull($registration->member_status, 'Unbestätigte Anmeldungen werden nicht abgefragt.');
        $this->assertSame([], $this->suchen);

        $token = null;
        Mail::assertQueued(EventRegistrationMail::class, function ($mail) use (&$token) {
            $token = $mail->token;

            return $mail->kind === 'doi';
        });
        $this->post("/anmeldung/bestaetigen/{$token}")->assertOk();

        $registration->refresh();
        $this->assertSame(['mitglied', '4711'], [$registration->member_status, $registration->member_number]);

        // Export und Datenschutzauskunft enthalten das Ergebnis.
        $export = app(ParticipantExport::class);
        $this->assertContains('Mitgliedsnummer', $export->headings($event));
        $this->assertContains('4711', $export->rows($event)[0]);
    }

    #[TestDox('Ohne eingerichteten Treiber: kein Abgleich, kein Hinweis im Einwilligungstext')]
    public function test_off_driver(): void
    {
        config(['mitglieder.driver' => 'off']);
        $event = $this->event();

        $this->assertFalse($event->checksMembers());
        $this->assertSame(config('dpsg.event_consent.version'), $event->consentVersion());
        $this->get('/veranstaltungen/woelflingslager')->assertOk()->assertDontSee('ob du Mitglied bist');
        $this->assertNotContains('Mitgliedsnummer', app(ParticipantExport::class)->headings($event));
    }

    #[TestDox('Backend: Abgleich starten, Spalte „Mitglied“, Entscheidung von Hand bleibt bei neuem Abgleich')]
    public function test_backend_actions(): void
    {
        $this->bestand = [
            $this->mitglied('1', 'Lea', 'Klein', '2010-02-02'),
            $this->mitglied('2', 'Lea', 'Klein', '2010-09-09'),
            $this->mitglied('3', 'Ben', 'Berg'),
        ];
        $event = $this->event();
        $lea = $this->anmeldung($event, 'Lea', 'Klein');
        $ben = $this->anmeldung($event, 'Ben', 'Berg');
        $offen = $this->anmeldung($event, 'Ben', 'Berg', email: 'x@example.org', status: 'pending');

        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());

        $table = Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->assertSee('noch nicht geprüft')
            ->callTableAction('checkMembers', data: ['alle' => false])
            ->assertHasNoTableActionErrors();

        $this->assertSame('unklar', $lea->fresh()->member_status);
        $this->assertSame('mitglied', $ben->fresh()->member_status);
        $this->assertNull($offen->fresh()->member_status);
        $this->assertSame(1, AuditLog::query()->where('action', 'event.member_check')->count());

        $table->callTableAction('memberDecision', $lea, data: ['status' => 'mitglied', 'number' => '2', 'group' => 'Stamm Test'])
            ->assertHasNoTableActionErrors();
        $this->assertTrue($lea->fresh()->member_manual);

        $table->callTableAction('checkMembers', data: ['alle' => true]);
        $this->assertSame(['mitglied', '2', true], [$lea->fresh()->member_status, $lea->fresh()->member_number, $lea->fresh()->member_manual]);
        $table->assertSee('Nr. 2')->assertSee('von Hand');
    }

    #[TestDox('NaMi 2: Anmeldung, Suche per search-multi, Nicht-Mitglieder und beendete Mitgliedschaften, Neuanmeldung bei abgelaufener Session')]
    public function test_nami_driver(): void
    {
        config(['mitglieder.nami.user' => '123456', 'mitglieder.nami.password' => 'geheim']);
        $logins = 0;
        $searches = 0;
        Http::fake(function (Request $request) use (&$logins, &$searches) {
            if (str_ends_with($request->url(), '/ica/rest/api/1/1/service/nami/auth/manual/sessionStartup')) {
                $logins++;
                $this->assertSame(['username' => '123456', 'password' => 'geheim', 'Login' => 'API'], $request->data());

                return Http::response(['statusCode' => 0, 'statusMessage' => ''], 200, ['Set-Cookie' => 'JSESSIONID=abc; Path=/ica']);
            }
            if (str_contains($request->url(), '/search-multi/result-list')) {
                $searches++;
                if ($searches === 1) {
                    return Http::response(['success' => false, 'message' => 'Session expired'], 200);
                }
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $values = json_decode($query['searchedValues'], true);
                $this->assertSame(['Mia', 'Muster'], [$values['vorname'], $values['nachname']]);

                return Http::response(['success' => true, 'data' => [
                    ['id' => 1, 'entries_mitgliedsNummer' => 4711, 'entries_vorname' => 'Mia', 'entries_nachname' => 'Muster', 'entries_geburtsDatum' => '2010-03-03 00:00:00', 'entries_email' => 'eltern@example.org', 'entries_gruppierung' => 'Stamm Sankt Georg', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied'],
                    ['id' => 2, 'entries_mitgliedsNummer' => 4712, 'entries_vorname' => 'Mia', 'entries_nachname' => 'Muster', 'entries_geburtsDatum' => '1980-01-01 00:00:00', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Nicht-Mitglied'],
                    ['id' => 3, 'entries_mitgliedsNummer' => 4713, 'entries_vorname' => 'Mia', 'entries_nachname' => 'Muster', 'entries_status' => 'Inaktiv', 'entries_mglType' => 'Mitglied'],
                ]]);
            }

            return Http::response('', 404);
        });

        $treffer = (new NamiVerzeichnis)->suche('Mia', 'Muster');

        $this->assertSame(2, $logins, 'Nach abgelaufener Session einmal neu anmelden.');
        $this->assertSame(['4711', '4712', '4713'], array_map(fn (Mitglied $m) => $m->nummer, $treffer));
        $this->assertSame([true, false, false], array_map(fn (Mitglied $m) => $m->aktiv, $treffer));
        $this->assertSame('2010-03-03', $treffer[0]->geburtsdatum?->toDateString());
        $this->assertSame('Stamm Sankt Georg', $treffer[0]->gruppierung);
    }

    #[TestDox('NaMi 2: falsche Zugangsdaten führen zu einer Fehlermeldung ohne Antworttext')]
    public function test_nami_login_failure(): void
    {
        config(['mitglieder.nami.user' => '123456', 'mitglieder.nami.password' => 'falsch']);
        Http::fake(['*' => Http::response(['statusCode' => 3000, 'statusMessage' => 'Benutzer 123456 unbekannt'], 200)]);

        try {
            (new NamiVerzeichnis)->pruefe();
            $this->fail('Exception erwartet');
        } catch (MitgliederverzeichnisException $e) {
            $this->assertStringContainsString('Anmeldung fehlgeschlagen', $e->getMessage());
            $this->assertStringNotContainsString('123456', $e->getMessage());
        }

        config(['mitglieder.driver' => 'nami']);
        $this->app->forgetInstance(Mitgliederverzeichnis::class);
        $this->app->singleton(Mitgliederverzeichnis::class, fn () => new NamiVerzeichnis);
        $this->artisan('mitglieder:check')->expectsOutputToContain('NaMi 2: NaMi-Anmeldung fehlgeschlagen')->assertFailed();
    }

    #[TestDox('NaMi 3 (hitobito): Service-Token, Filter auf Name, Stamm aus layer_group, Austritt, Mitgliedsnummer-Attribut')]
    public function test_hitobito_driver(): void
    {
        config([
            'mitglieder.hitobito.url' => 'https://nami3.example.org/',
            'mitglieder.hitobito.token' => 'tok-123',
            'mitglieder.hitobito.member_number_attribute' => 'membership_number',
        ]);
        Http::fake(function (Request $request) {
            $this->assertSame('tok-123', $request->header('X-TOKEN')[0]);
            $this->assertStringStartsWith('https://nami3.example.org/api/people?', $request->url());
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame('Muster', $query['filter']['last_name']['eq']);
            $this->assertSame('Mia', $query['filter']['first_name']['prefix']);
            $this->assertSame('first_name,last_name,birthday,email,exit_date,membership_number', $query['fields']['people']);

            return Http::response([
                'data' => [
                    ['id' => '501', 'type' => 'people', 'attributes' => ['first_name' => 'Mia', 'last_name' => 'Muster', 'birthday' => '2010-03-03', 'email' => null, 'exit_date' => null, 'membership_number' => '4711'],
                        'relationships' => ['layer_group' => ['data' => ['type' => 'groups', 'id' => '77']]]],
                    ['id' => '502', 'type' => 'people', 'attributes' => ['first_name' => 'Mia', 'last_name' => 'Muster', 'exit_date' => '2020-12-31']],
                ],
                'included' => [['id' => '77', 'type' => 'groups', 'attributes' => ['name' => 'Stamm Sankt Georg']]],
            ]);
        });

        $treffer = (new HitobitoVerzeichnis)->suche('Mia', 'Muster');

        $this->assertSame(['4711', '502'], array_map(fn (Mitglied $m) => $m->nummer, $treffer));
        $this->assertSame([true, false], array_map(fn (Mitglied $m) => $m->aktiv, $treffer));
        $this->assertSame('Stamm Sankt Georg', $treffer[0]->gruppierung);
        $this->assertNull($treffer[1]->geburtsdatum);
    }

    #[TestDox('NaMi 3 (hitobito): abgelehnter Token wird verständlich gemeldet')]
    public function test_hitobito_forbidden(): void
    {
        config(['mitglieder.hitobito.url' => 'https://nami3.example.org', 'mitglieder.hitobito.token' => 'tok']);
        Http::fake(['*' => Http::response(['errors' => [['status' => '403']]], 403)]);

        $this->expectException(MitgliederverzeichnisException::class);
        $this->expectExceptionMessage('lehnt den Service-Token ab');
        (new HitobitoVerzeichnis)->pruefe();
    }

    private function backendLogin(): void
    {
        $user = User::query()->create(['name' => 'Lea Leitung', 'email' => 'leitung@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());
    }

    /**
     * NaMi-Attrappe für die persönliche Anmeldung: Konto 777/richtig, Suche
     * nur mit dessen Session-Cookie, optional „Session expired“.
     *
     * @param  array{logins: int, cookies: list<string>}  $protokoll
     */
    private function namiPersoenlich(array &$protokoll, bool $abgelaufen = false, bool $einmalAbgelaufen = false): void
    {
        config(['mitglieder.nami.auth' => 'persoenlich']);
        $protokoll = ['logins' => 0, 'cookies' => []];
        Http::fake(function (Request $request) use (&$protokoll, $abgelaufen, $einmalAbgelaufen) {
            if (str_ends_with($request->url(), '/auth/manual/sessionStartup')) {
                $protokoll['logins']++;
                $ok = $request->data() === ['username' => '777', 'password' => 'richtig', 'Login' => 'API'];

                return $ok
                    ? Http::response(['statusCode' => 0], 200, ['Set-Cookie' => 'JSESSIONID=persoenlich777-'.$protokoll['logins'].'; Path=/ica'])
                    : Http::response(['statusCode' => 3000, 'statusMessage' => 'Benutzer 777 unbekannt'], 200);
            }
            if (str_contains($request->url(), '/search-multi/result-list')) {
                $protokoll['cookies'][] = $request->header('Cookie')[0] ?? '';
                if ($abgelaufen || ($einmalAbgelaufen && count($protokoll['cookies']) === 1)) {
                    return Http::response(['success' => false, 'message' => 'Session expired'], 200);
                }
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $nachname = json_decode($query['searchedValues'], true)['nachname'] ?? '';

                return Http::response(['success' => true, 'data' => $nachname === 'Leitung'
                    ? [['id' => 7, 'entries_mitgliedsNummer' => 777, 'entries_vorname' => 'Lea', 'entries_nachname' => 'Leitung', 'entries_gruppierung' => 'Stamm Test', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied']]
                    : [['id' => 1, 'entries_mitgliedsNummer' => 4711, 'entries_vorname' => 'Mia', 'entries_nachname' => 'Muster', 'entries_geburtsDatum' => '2010-03-03 00:00:00', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied']],
                ]);
            }

            return Http::response('', 200);
        });
        // Echte NaMi-Anbindung statt des Verzeichnisses im Speicher.
        $this->app->forgetInstance(Mitgliederverzeichnis::class);
        $this->app->singleton(Mitgliederverzeichnis::class, fn () => new NamiVerzeichnis);
    }

    #[TestDox('NaMi 2 persönlich: Anmeldung mit eigenem Konto im Backend, nur das Session-Cookie geht (verschlüsselt) in die Warteschlange')]
    public function test_personal_nami_login(): void
    {
        $protokoll = [];
        $this->namiPersoenlich($protokoll);
        Queue::fake();
        $event = $this->event();
        $mia = $this->anmeldung($event, 'Mia', 'Muster');
        $this->backendLogin();

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('checkMembers', data: ['nami_user' => ' 777 ', 'nami_password' => 'richtig', 'alle' => false])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Abgleich gestartet – die Spalte „Mitglied“ füllt sich in den nächsten Minuten.');

        $this->assertSame(1, $protokoll['logins']);
        $this->assertFalse(auth()->user()->fresh()->hasNamiZugang(), 'Ohne Häkchen nichts gespeichert');
        $this->assertSame(1, AuditLog::query()->where('action', 'event.member_check')->count());

        $job = null;
        Queue::assertPushed(CheckEventMembers::class, function (CheckEventMembers $pushed) use (&$job) {
            $job = $pushed;

            return true;
        });
        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertNotNull($job->namiSession);
        $this->assertStringNotContainsString('richtig', serialize($job), 'Kein Passwort im Job');

        // Der Job arbeitet nur mit dem Cookie, ohne neue Anmeldung.
        $job->handle(app(MitgliederAbgleich::class));
        $this->assertSame(['mitglied', '4711'], [$mia->fresh()->member_status, $mia->fresh()->member_number]);
        $this->assertSame(1, $protokoll['logins']);
        $this->assertStringContainsString('JSESSIONID=persoenlich777-1', $protokoll['cookies'][0]);
    }

    #[TestDox('NaMi 2 persönlich: falsches Passwort – Fehlermeldung, kein Abgleich; ohne Eingabe Pflichtfelder')]
    public function test_personal_nami_login_failure(): void
    {
        $protokoll = [];
        $this->namiPersoenlich($protokoll);
        Queue::fake();
        $event = $this->event();
        $this->anmeldung($event, 'Mia', 'Muster');
        $this->backendLogin();

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('checkMembers', data: ['nami_user' => '777', 'nami_password' => 'falsch'])
            ->assertNotified('NaMi-Anmeldung fehlgeschlagen');
        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('checkMembers', data: ['nami_user' => '', 'nami_password' => ''])
            ->assertHasTableActionErrors(['nami_user' => 'required', 'nami_password' => 'required']);

        Queue::assertNothingPushed();
        $this->assertSame(0, AuditLog::query()->where('action', 'event.member_check')->count());
    }

    #[TestDox('NaMi 2 persönlich: abgelaufene Sitzung beendet den Lauf ohne Neuanmeldung; ohne Sitzung und nach Bestätigung kein Abgleich')]
    public function test_personal_nami_session_expired(): void
    {
        $protokoll = [];
        $this->namiPersoenlich($protokoll, abgelaufen: true);
        $event = $this->event();
        $mia = $this->anmeldung($event, 'Mia', 'Muster');

        $session = (new NamiVerzeichnis)->anmelden('777', 'richtig');
        Queue::fake();
        (new CheckEventMembers($event->id, namiSession: $session))->handle(app(MitgliederAbgleich::class));
        $this->assertNull($mia->fresh()->member_status, 'bleibt „noch nicht geprüft“');
        $this->assertSame(1, $protokoll['logins'], 'keine Neuanmeldung ohne Passwort');
        Queue::assertNothingPushed();

        (new CheckEventMembers($event->id))->handle(app(MitgliederAbgleich::class));
        $this->assertSame(1, count($protokoll['cookies']), 'ohne Sitzung keine Abfrage');

        // Neue Anmeldung bestätigen: keine automatische Prüfung.
        $this->post('/veranstaltungen/woelflingslager/anmeldung', [
            'first_name' => 'Ole', 'last_name' => 'Neu', 'email' => 'ole@example.org',
            'birth_year' => 2010, 'by_guardian' => '1', 'guardian_name' => 'Eva Neu', 'guardian_consent' => '1', 'consent' => '1',
        ])->assertSessionHasNoErrors();
        $token = null;
        Mail::assertQueued(EventRegistrationMail::class, function ($mail) use (&$token) {
            $token = $mail->token;

            return $mail->kind === 'doi';
        });
        $this->post("/anmeldung/bestaetigen/{$token}")->assertOk();
        Queue::assertNotPushed(CheckEventMembers::class);
    }

    #[TestDox('NaMi 2 persönlich: mitglieder:check prüft nur die Erreichbarkeit, ohne Anmeldung')]
    public function test_personal_nami_check_command(): void
    {
        $protokoll = [];
        $this->namiPersoenlich($protokoll);

        $this->artisan('mitglieder:check')->expectsOutputToContain('NaMi 2: erreichbar')->assertSuccessful();
        $this->assertSame(0, $protokoll['logins']);
    }

    #[TestDox('NaMi-Zugang im Profil: nur gespeichert, wenn die Anmeldung klappt; verschlüsselt; Selbsttest, Ampel, Löschen')]
    public function test_profile_nami_access(): void
    {
        $protokoll = [];
        $this->namiPersoenlich($protokoll);
        $this->backendLogin();
        $user = auth()->user();

        $this->get(NamiZugang::getUrl())->assertOk()->assertSee('NaMi-Server')->assertSee('nicht gespeichert');

        Livewire::test(NamiZugang::class)
            ->set('data.nami_user', '777')->set('data.nami_password', 'falsch')
            ->call('save')
            ->assertNotified('Nicht gespeichert: NaMi-Anmeldung fehlgeschlagen');
        $this->assertFalse($user->fresh()->hasNamiZugang());

        Livewire::test(NamiZugang::class)
            ->set('data.nami_user', ' 777 ')->set('data.nami_password', 'richtig')
            ->call('save')
            ->assertNotified('NaMi-Zugang gespeichert');
        $user->refresh();
        $this->assertSame(['777', 'richtig'], [$user->nami_user, $user->nami_password]);
        $roh = (array) DB::table('users')->where('id', $user->id)->first(['nami_user', 'nami_password']);
        $this->assertStringNotContainsString('richtig', implode(' ', $roh), 'verschlüsselt gespeichert');
        $this->assertArrayNotHasKey('nami_password', $user->toArray());
        $this->assertSame(1, AuditLog::query()->where('action', 'nami.zugang.gespeichert')->count());
        $zip = new \ZipArchive;
        $zip->open(app(PrivacyRequests::class)->export('leitung@example.org'));
        $auskunft = (string) $zip->getFromName('auskunft.json');
        $this->assertStringContainsString('"mitgliedsnummer": "777"', $auskunft);
        $this->assertStringNotContainsString('richtig', $auskunft);

        // Passwortfeld leer lassen = gespeichertes behalten; Selbsttest findet die eigene Nummer.
        Livewire::test(NamiZugang::class)
            ->assertSee('Anmeldung klappt')
            ->set('data.nami_password', '')->call('save')->assertNotified('NaMi-Zugang gespeichert')
            ->call('test')
            ->assertSet('selbsttest', 'Anmeldung und Suche klappen: Du wirst in NaMi als „Lea Leitung“ geführt (Stamm Test), aktives Mitglied.');
        $this->assertSame('richtig', $user->fresh()->nami_password);

        // Passwort in NaMi geändert: rote Ampel.
        $user->forceFill(['nami_password' => 'veraltet'])->save();
        Livewire::test(NamiZugang::class)->call('test')->assertNotified('NaMi-Test fehlgeschlagen');
        $this->assertSame(NamiAmpel::FAIL, NamiAmpel::status($user->fresh()));

        $user->forceFill(['nami_password' => 'richtig'])->save();
        $user->update(['status' => 'disabled']);
        $this->assertFalse($user->fresh()->hasNamiZugang(), 'Sperren löscht den Zugang');
        $user->update(['status' => 'active']);
        $user->forceFill(['nami_user' => '777', 'nami_password' => 'richtig'])->save();

        Livewire::test(NamiZugang::class)->call('delete');
        $this->assertFalse($user->fresh()->hasNamiZugang());
        $this->assertSame(NamiAmpel::OFF, NamiAmpel::status($user->fresh()));

        // Technisches Konto: keine Profilseite.
        config(['mitglieder.nami.auth' => 'konto']);
        $this->get(NamiZugang::getUrl())->assertForbidden();
    }

    #[TestDox('Mit gespeichertem Zugang: keine Eingabe, Job trägt kein Passwort und meldet sich bei abgelaufener Sitzung neu an')]
    public function test_check_with_stored_nami_access(): void
    {
        $protokoll = [];
        $this->namiPersoenlich($protokoll, einmalAbgelaufen: true);
        Queue::fake();
        $event = $this->event();
        $mia = $this->anmeldung($event, 'Mia', 'Muster');
        $this->backendLogin();
        auth()->user()->forceFill(['nami_user' => '777', 'nami_password' => 'richtig'])->save();

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('checkMembers', data: ['alle' => false])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Abgleich gestartet – die Spalte „Mitglied“ füllt sich in den nächsten Minuten.');

        $job = null;
        Queue::assertPushed(CheckEventMembers::class, function (CheckEventMembers $pushed) use (&$job) {
            $job = $pushed;

            return true;
        });
        $this->assertSame(auth()->id(), $job->namiUserId);
        $this->assertStringNotContainsString('richtig', serialize($job));
        $this->assertNotNull(auth()->user()->fresh()->nami_checked_at);

        // Erste Suche: Sitzung abgelaufen → Neuanmeldung mit dem gespeicherten Zugang.
        $job->handle(app(MitgliederAbgleich::class));
        $this->assertSame('mitglied', $mia->fresh()->member_status);
        $this->assertSame(2, $protokoll['logins']);
        $this->assertStringContainsString('JSESSIONID=persoenlich777-2', $protokoll['cookies'][1]);
    }

    #[TestDox('Zugang direkt beim Prüfen speichern; Statusseite zeigt NaMi als eigene Zeile')]
    public function test_save_access_from_dialog_and_status(): void
    {
        $protokoll = [];
        $this->namiPersoenlich($protokoll);
        Queue::fake();
        $event = $this->event();
        $this->backendLogin();

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('checkMembers', data: ['nami_user' => '777', 'nami_password' => 'richtig', 'nami_speichern' => true])
            ->assertHasNoTableActionErrors();
        $this->assertTrue(auth()->user()->fresh()->hasNamiZugang());
        Queue::assertPushed(CheckEventMembers::class, fn (CheckEventMembers $job) => $job->namiUserId === auth()->id());

        $checks = collect(app(HealthChecks::class)->run())->keyBy('key');
        $this->assertSame([HealthChecks::OK, 'Erreichbar.'], [$checks['nami']['status'], $checks['nami']['message']]);
    }
}
