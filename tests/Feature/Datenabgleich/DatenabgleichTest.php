<?php

namespace Tests\Feature\Datenabgleich;

use App\Filament\Resources\Datenabgleich\DatenabgleichResource;
use App\Filament\Resources\Datenabgleich\Pages\ListDatenabgleich;
use App\Jobs\DatenabgleichAusfuehren;
use App\Mail\DatenmeldungMail;
use App\Models\DatenabgleichZeile;
use App\Models\Datenmeldung;
use App\Models\Event;
use App\Models\User;
use App\Services\DatenabgleichExport;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Services\PrivacyRequests;
use App\Support\Datenabgleich;
use App\Support\Features;
use App\Support\SpamGuard;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * NaMi-Datenabgleich (docs/pflichtenheft-nami-datenabgleich.md): Formular mit
 * Bestätigung, Quellen, Vergleich, Abgleich gegen NaMi (nur lesend), Liste,
 * Exporte, Löschfristen und Datenschutz-Anfragen.
 */
class DatenabgleichTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $schreibversuche = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Filament::setCurrentPanel('admin');
        config(['mitglieder.driver' => 'nami', 'mitglieder.nami.auth' => 'konto', 'mitglieder.nami.user' => '123456', 'mitglieder.nami.password' => 'geheim',
            'dpsg.einrichtung.nummer' => '210601', 'dpsg.site.level' => 'Stamm']);
        Features::setzen(Features::NAMI_DATENABGLEICH, true);

        Http::fake(function (Request $request) {
            if ($request->method() !== 'GET' && ! str_ends_with($request->url(), '/auth/manual/sessionStartup')) {
                $this->schreibversuche[] = $request->method().' '.$request->url();
            }
            $url = $request->url();
            if (str_ends_with($url, '/auth/manual/sessionStartup')) {
                return Http::response(['statusCode' => 0], 200, ['Set-Cookie' => 'JSESSIONID=abc; Path=/ica']);
            }
            if (preg_match('#/gruppierung/node/(\d+)#', $url, $m)) {
                return Http::response(['success' => true, 'data' => match ((int) $m[1]) {
                    1 => [['id' => 10, 'descriptor' => 'Diözesanverband Trier 210000']],
                    10 => [['id' => 20, 'descriptor' => 'Bezirk Saar-Hochwald 210600']],
                    20 => [['id' => 30, 'descriptor' => 'Graf Folke Bernadotte 210601']],
                    default => [],
                }]);
            }
            if (str_contains($url, '/gruppierung/gruppierung/30/flist')) {
                return Http::response(['success' => true, 'data' => [
                    ['id' => 101, 'entries_vorname' => 'Mia Sophie', 'entries_nachname' => 'Muster', 'entries_mitgliedsNummer' => 4711, 'entries_geburtsDatum' => '2014-03-03 00:00:00', 'entries_mglType' => 'Mitglied'],
                    ['id' => 102, 'entries_vorname' => 'Ben', 'entries_nachname' => 'Beispiel', 'entries_mitgliedsNummer' => 4712, 'entries_geburtsDatum' => '2012-05-05 00:00:00', 'entries_mglType' => 'Mitglied'],
                    ['id' => 103, 'entries_vorname' => 'Ella', 'entries_nachname' => 'Eltern', 'entries_mitgliedsNummer' => 4713, 'entries_mglType' => 'Nicht-Mitglied'],
                ]]);
            }
            if (preg_match('#/gruppierung/gruppierung/30/(\d+)$#', $url, $m)) {
                return Http::response(['success' => true, 'data' => match ((int) $m[1]) {
                    101 => ['geburtsDatum' => '2014-03-03 00:00:00', 'geschlecht' => 'weiblich', 'strasse' => 'Hauptstr. 1', 'plz' => '66740', 'ort' => 'Saarlouis',
                        'telefon1' => '+49 6831 12345', 'email' => '', 'emailVertretungsberechtigter' => 'alt@example.org', 'mitgliedsNummer' => 4711],
                    102 => ['geburtsDatum' => '2012-05-05 00:00:00', 'geschlechtId' => 19, 'strasse' => 'Am Bach 3', 'plz' => '66740', 'ort' => 'Saarlouis'],
                    default => [],
                }]);
            }

            return Http::response('', 404);
        });
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => $role, 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global', 'scope_key' => null]);

        return $user->fresh();
    }

    /** @param array<string, mixed> $daten */
    private function meldung(array $daten = [], bool $bestaetigt = true): Datenmeldung
    {
        $daten = ['vorname' => 'Mia', 'nachname' => 'Muster', 'geburtsdatum' => '2014-03-03', 'geschlecht' => 'w', 'strasse' => 'Hauptstraße 1', 'plz' => '66740',
            'ort' => 'Saarlouis', 'telefon' => '06831 12345', 'email' => null, 'email_eltern' => 'neu@example.org', 'rolle' => 'erziehungsberechtigt', ...$daten];

        return Datenmeldung::query()->create([
            'daten' => $daten, 'email_hash' => Datenmeldung::hash($daten['email']), 'email_eltern_hash' => Datenmeldung::hash($daten['email_eltern']),
            'token_hash' => hash('sha256', (string) random_int(1, PHP_INT_MAX)), 'bestaetigt_am' => $bestaetigt ? now() : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function formular(array $daten = []): array
    {
        return ['_t' => SpamGuard::token(), 'vorname' => 'Mia', 'nachname' => 'Muster', 'geburtsdatum' => '2014-03-03', 'geschlecht' => 'w',
            'strasse' => 'Hauptstraße 1', 'plz' => '66740', 'ort' => 'Saarlouis', 'telefon' => '06831 12345', 'email' => '',
            'email_eltern' => 'Eltern@Example.org', 'rolle' => 'erziehungsberechtigt', 'einwilligung' => '1', ...$daten];
    }

    #[TestDox('Ausgeschaltet: Formular und Liste gibt es nicht')]
    public function test_schalter(): void
    {
        Features::setzen(Features::NAMI_DATENABGLEICH, false);
        $this->get('/meine-daten')->assertNotFound();
        $this->actingAs($this->user('verwaltung'));
        $this->assertFalse(DatenabgleichResource::canAccess());
    }

    #[TestDox('Formular: Bestätigung per Mail an die ausfüllende Person, erst dann zählt die Meldung; eine neue ersetzt die alte')]
    public function test_formular_und_bestaetigung(): void
    {
        $this->get('/meine-daten')->assertOk()->assertSee('Erziehungsberechtigte');
        $formular = $this->formular();
        $this->travel(5)->seconds();
        $this->post('/meine-daten', $formular)->assertRedirect(route('datenmeldung.danke'));

        $meldung = Datenmeldung::query()->sole();
        $this->assertNull($meldung->bestaetigt_am);
        $this->assertSame(Datenmeldung::hash('eltern@example.org'), $meldung->email_eltern_hash);
        $this->assertStringNotContainsString('Hauptstra', (string) DB::table('datenmeldungen')->value('daten'), 'verschlüsselt gespeichert');
        $this->assertCount(0, Datenabgleich::quellen());

        $link = null;
        Mail::assertQueued(DatenmeldungMail::class, function (DatenmeldungMail $mail) use (&$link) {
            $link = $mail->link;

            return $mail->hasTo('Eltern@Example.org') && $mail->mitglied === 'Mia Muster';
        });
        $this->get($link)->assertOk();
        $this->assertNotNull($meldung->fresh()->bestaetigt_am);
        $this->assertCount(1, Datenabgleich::quellen());

        // Zweite Meldung derselben Person ersetzt nach Bestätigung die erste.
        $token = SpamGuard::token();
        $this->travel(5)->seconds();
        $this->post('/meine-daten', $this->formular(['_t' => $token, 'ort' => 'Wallerfangen']))->assertRedirect(route('datenmeldung.danke'));
        $this->get(Mail::queued(DatenmeldungMail::class)->last()->link)->assertOk();
        $this->assertSame(['Wallerfangen'], Datenmeldung::query()->whereNotNull('bestaetigt_am')->get()->map(fn ($m) => $m->daten['ort'])->all());

        $this->get('/meine-daten/bestaetigen/'.str_repeat('x', 40))->assertNotFound();
    }

    #[TestDox('Formular: ohne Mail-Adresse der ausfüllenden Person und ohne Einwilligung keine Meldung')]
    public function test_formular_pflichtangaben(): void
    {
        // Je Absendung ein frischer Zeitstempel, wie beim Neuladen nach einem Fehler.
        $frisch = function (): string {
            $token = SpamGuard::token();
            $this->travel(5)->seconds();

            return $token;
        };
        $this->post('/meine-daten', $this->formular(['_t' => $frisch(), 'email_eltern' => '']))->assertSessionHasErrors('email_eltern');
        $this->post('/meine-daten', $this->formular(['_t' => $frisch(), 'einwilligung' => null]))->assertSessionHasErrors('einwilligung');
        $this->post('/meine-daten', $this->formular(['_t' => $frisch(), 'rolle' => 'mitglied']))->assertSessionHasErrors('email');
        $this->assertSame(0, Datenmeldung::query()->count());
        Mail::assertNothingQueued();
    }

    #[TestDox('Quellen: Anmeldungen der letzten 12 Monate mit Anschrift; je Person die neueste Angabe')]
    public function test_quellen(): void
    {
        $event = Event::query()->create(['title' => 'Lager', 'slug' => 'lager', 'status' => 'open', 'starts_at' => now()->addMonth()]);
        $alt = $event->registrations()->create(['status' => 'confirmed', 'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'eltern@example.org', 'guardian_name' => 'Eva Muster',
            'data' => ['strasse' => 'Alte Gasse 2', 'geburtsdatum' => '2014-03-03'], 'confirmed_at' => now()->subDays(10), 'consent_at' => now(), 'consent_text_version' => 'x']);
        $event->registrations()->create(['status' => 'confirmed', 'first_name' => 'Ohne', 'last_name' => 'Anschrift', 'email' => 'o@example.org', 'data' => [], 'consent_at' => now(), 'consent_text_version' => 'x']);
        $event->registrations()->create(['status' => 'cancelled', 'first_name' => 'Ben', 'last_name' => 'Beispiel', 'email' => 'b@example.org', 'data' => ['strasse' => 'X 1'], 'consent_at' => now(), 'consent_text_version' => 'x']);

        $quellen = Datenabgleich::quellen();
        $this->assertCount(1, $quellen);
        $this->assertSame(['anmeldung', null, 'eltern@example.org'], [$quellen[0]['quelle'], $quellen[0]['werte']['email'], $quellen[0]['werte']['email_eltern']]);

        $this->meldung();
        $quellen = Datenabgleich::quellen();
        $this->assertCount(1, $quellen, 'gleiche Person nur einmal');
        $this->assertSame('meldung', $quellen[0]['quelle'], 'die neuere Meldung zählt');
        $this->assertNotNull($alt);
    }

    #[TestDox('Vergleich ohne Kleinkram: Str./Straße, Groß-/Kleinschreibung, Telefon nach Ziffern; leere NaMi-Felder als „fehlt“')]
    public function test_vergleichen(): void
    {
        $nami = ['geburtsDatum' => '2014-03-03 00:00:00', 'geschlecht' => 'weiblich', 'strasse' => 'HAUPTSTR. 1', 'plz' => '66740', 'ort' => 'saarlouis',
            'telefon1' => '', 'telefon2' => '+49 (6831) 12345', 'email' => '', 'emailVertretungsberechtigter' => 'eltern@example.org'];
        $werte = ['geburtsdatum' => '2014-03-03', 'geschlecht' => 'w', 'strasse' => 'Hauptstraße 1', 'plz' => '66740', 'ort' => 'Saarlouis',
            'telefon' => '06831/12345', 'email' => '', 'email_eltern' => 'Eltern@Example.org'];
        $this->assertSame([], Datenabgleich::vergleichen($werte, $nami));

        $felder = Datenabgleich::vergleichen([...$werte, 'ort' => 'Wallerfangen', 'email' => 'mia@example.org', 'geburtsdatum' => '2014-03-04'], $nami);
        $this->assertSame([
            ['feld' => 'Geburtsdatum', 'meldung' => '04.03.2014', 'nami' => '03.03.2014'],
            ['feld' => 'Ort', 'meldung' => 'Wallerfangen', 'nami' => 'saarlouis'],
            ['feld' => 'E-Mail', 'meldung' => 'mia@example.org', 'nami' => 'fehlt'],
        ], $felder);
    }

    #[TestDox('Abgleich über die Warteschlange: Treffer, Abweichungen, nicht gefunden; NaMi wird nur gelesen')]
    public function test_abgleich(): void
    {
        config(['mitglieder.per_run' => 1]);
        $this->meldung();
        $this->meldung(['vorname' => 'Ben', 'nachname' => 'Beispiel', 'geburtsdatum' => '2012-05-05', 'geschlecht' => 'm', 'strasse' => 'Am Bach 3', 'telefon' => null, 'email_eltern' => null]);
        $this->meldung(['vorname' => 'Unbekannt', 'nachname' => 'Person', 'geburtsdatum' => '2015-01-01']);
        DatenabgleichZeile::query()->create(['quelle' => 'meldung', 'quelle_id' => 999, 'status' => 'stimmt', 'ergebnis' => []]);
        $this->assertInstanceOf(ShouldBeEncrypted::class, new DatenabgleichAusfuehren('210601'));

        DatenabgleichAusfuehren::dispatchSync('210601');

        $this->assertSame(3, DatenabgleichZeile::query()->count(), 'alte Liste ersetzt');
        $mia = DatenabgleichZeile::query()->get()->first(fn ($z) => $z->ergebnis['name'] === 'Mia Muster');
        $this->assertSame('abweichung', $mia->status);
        $this->assertSame('4711', $mia->ergebnis['nummer']);
        $this->assertSame([['feld' => 'E-Mail Erziehungsberechtigte', 'meldung' => 'neu@example.org', 'nami' => 'alt@example.org']], $mia->ergebnis['felder']);
        $this->assertSame('stimmt', DatenabgleichZeile::query()->get()->first(fn ($z) => $z->ergebnis['name'] === 'Ben Beispiel')->status);
        $this->assertSame('nicht_gefunden', DatenabgleichZeile::query()->get()->first(fn ($z) => $z->ergebnis['name'] === 'Unbekannt Person')->status);
        $this->assertStringNotContainsString('alt@example.org', (string) DB::table('datenabgleich_zeilen')->pluck('ergebnis')->implode(' '), 'verschlüsselt gespeichert');
        $this->assertSame('fertig', Datenabgleich::stand()['status']);
        $this->assertSame([], $this->schreibversuche, 'Kluft schreibt nichts in NaMi');
    }

    #[TestDox('Liste: Verwaltung und Kasse, nicht Leitung; abhaken und Exporte')]
    public function test_liste_und_rechte(): void
    {
        $this->meldung();
        Datenabgleich::abgleichen(new NamiVerzeichnis, '210601');
        $zeile = DatenabgleichZeile::query()->sole();

        $this->actingAs($this->user('leitung'));
        $this->assertFalse(DatenabgleichResource::canAccess());

        $kasse = $this->user('kasse');
        $this->actingAs($kasse);
        $this->assertTrue(DatenabgleichResource::canAccess());
        $this->assertSame('1', DatenabgleichResource::getNavigationBadge());
        Livewire::test(ListDatenabgleich::class)->assertOk()->assertCanSeeTableRecords([$zeile])
            ->callTableAction('erledigt', $zeile);
        $this->assertNotNull($zeile->fresh()->erledigt_am);
        $this->assertSame($kasse->id, $zeile->fresh()->erledigt_von);

        $export = app(DatenabgleichExport::class);
        $this->assertStringStartsWith('%PDF', $export->pdf());
        ob_start();
        $export->xlsx();
        $this->assertStringStartsWith('PK', (string) ob_get_clean());
    }

    #[TestDox('Löschfristen: unbestätigt 48 Stunden, bestätigt 6 Monate, Ergebnisse 60 Tage')]
    public function test_aufraeumen(): void
    {
        $offen = $this->meldung(bestaetigt: false);
        $bestaetigt = $this->meldung(['vorname' => 'Ben']);
        DatenabgleichZeile::query()->create(['quelle' => 'meldung', 'quelle_id' => $bestaetigt->id, 'status' => 'stimmt', 'ergebnis' => []]);

        $this->travel(49)->hours();
        $this->artisan('datenabgleich:aufraeumen')->assertSuccessful();
        $this->assertNull($offen->fresh());
        $this->assertNotNull($bestaetigt->fresh());
        $this->assertSame(1, DatenabgleichZeile::query()->count());

        $this->travel(60)->days();
        $this->artisan('datenabgleich:aufraeumen')->assertSuccessful();
        $this->assertSame(0, DatenabgleichZeile::query()->count());

        $this->travel(5)->months();
        $this->artisan('datenabgleich:aufraeumen')->assertSuccessful();
        $this->assertNull($bestaetigt->fresh());
    }

    #[TestDox('Datenschutz-Anfrage: Auskunft und Löschen über die Mail des Mitglieds oder der Eltern')]
    public function test_datenschutz(): void
    {
        $meldung = $this->meldung();
        DatenabgleichZeile::query()->create(['quelle' => 'meldung', 'quelle_id' => $meldung->id, 'status' => 'stimmt', 'ergebnis' => []]);
        $this->meldung(['vorname' => 'Fremd', 'email_eltern' => 'fremd@example.org']);
        $privacy = app(PrivacyRequests::class);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($privacy->export('NEU@example.org')));
        $export = json_decode((string) $zip->getFromName('auskunft.json'), true);
        $this->assertCount(1, $export['datenmeldungen']);
        $this->assertSame('Hauptstraße 1', $export['datenmeldungen'][0]['angaben']['strasse']);

        $result = $privacy->delete('neu@example.org');
        $this->assertSame(1, $result['datenmeldungen']);
        $this->assertNull($meldung->fresh());
        $this->assertSame(0, DatenabgleichZeile::query()->count());
        $this->assertSame(1, Datenmeldung::query()->count());
    }
}
