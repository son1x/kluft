<?php

namespace Tests\Feature\Zuschuss;

use App\Filament\Pages\ProKopfZuschussPage;
use App\Models\BeitragsMitglied;
use App\Models\User;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Services\Zuschuss\ProKopfNami;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Pro-Kopf-Zuschuss der Kreisstadt Saarlouis direkt aus NaMi (ZU-19,
 * ProKopfNami): dieselben Regeln wie beim Datei-Upload, Anschriften in
 * Etappen, nichts gespeichert.
 */
class ProKopfNamiTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $liste = [];

    /** @var array<int, array<string, mixed>|null> null = NaMi verweigert die Details */
    private array $details = [];

    /** @var list<int> */
    private array $detailAbfragen = [];

    private bool $listeKaputt = false;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        config(['mitglieder.driver' => 'nami', 'mitglieder.nami.auth' => 'konto', 'mitglieder.nami.user' => '123456', 'mitglieder.nami.password' => 'geheim',
            'dpsg.einrichtung.nummer' => '210601', 'dpsg.site.level' => 'Stamm', 'mitglieder.per_run' => 2]);
        Features::setzen(Features::ZUSCHUESSE, true);

        // Stichtag 01.01.2027: unter 18 heißt geboren nach dem 01.01.2009.
        $this->liste = [
            ['id' => 101, 'entries_vorname' => 'Mia', 'entries_nachname' => 'Muster', 'entries_geburtsDatum' => '2015-05-04 00:00:00', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied'],
            ['id' => 102, 'entries_vorname' => 'Jan', 'entries_nachname' => 'Jung', 'entries_geburtsDatum' => '2009-01-02 00:00:00', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied'],
            ['id' => 103, 'entries_vorname' => 'Ali', 'entries_nachname' => 'Auer', 'entries_geburtsDatum' => '2012-07-07 00:00:00', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied'],
            ['id' => 104, 'entries_vorname' => 'Anna', 'entries_nachname' => 'Alt', 'entries_geburtsDatum' => '2009-01-01 00:00:00', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied'],
            ['id' => 105, 'entries_vorname' => 'Paul', 'entries_nachname' => 'Probe', 'entries_geburtsDatum' => '2016-01-01 00:00:00', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Schnuppermitglied'],
            ['id' => 106, 'entries_vorname' => 'Ella', 'entries_nachname' => 'Eltern', 'entries_geburtsDatum' => '1980-01-01 00:00:00', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Nicht-Mitglied'],
            ['id' => 107, 'entries_vorname' => 'Wilma', 'entries_nachname' => 'Weg', 'entries_geburtsDatum' => '2014-01-01 00:00:00', 'entries_status' => 'Inaktiv', 'entries_mglType' => 'Mitglied'],
            ['id' => 108, 'entries_vorname' => 'Otto', 'entries_nachname' => 'Ohne', 'entries_geburtsDatum' => '', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied'],
        ];
        $this->details = [
            101 => ['strasse' => 'Hauptstraße 1', 'plz' => '66740', 'ort' => 'Saarlouis'],
            102 => ['strasse' => 'Weg 3', 'plz' => '66740', 'ort' => 'Saarlouis'],
            103 => ['strasse' => 'Gasse 7', 'plz' => '66740', 'ort' => 'Saarlouis'],
        ];

        Http::fake(function (Request $request) {
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
                return $this->listeKaputt ? Http::response('<html>Wartung</html>', 503) : Http::response(['success' => true, 'data' => $this->liste]);
            }
            if (preg_match('#/gruppierung/gruppierung/30/(\d+)$#', $url, $m)) {
                $this->detailAbfragen[] = (int) $m[1];
                $details = $this->details[(int) $m[1]] ?? null;

                return $details === null
                    ? Http::response(['success' => false, 'message' => 'Keine Berechtigung'], 200)
                    : Http::response(['success' => true, 'data' => $details]);
            }

            return Http::response('', 404);
        });
    }

    private function kasse(): User
    {
        $user = User::query()->create(['name' => 'Kasse', 'email' => 'kasse@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'kasse', 'scope_type' => 'global']);

        return $user->fresh();
    }

    #[TestDox('Dienst: dieselben Regeln wie beim Datei-Upload; Anschriften nur für die Jugendlichen, in Etappen')]
    public function test_dienst(): void
    {
        $dienst = new ProKopfNami;
        $nami = new NamiVerzeichnis;
        $stand = $dienst->beginnen($nami, '210601', Carbon::create(2027, 1, 1));

        $this->assertSame([101, 102, 103], array_column($stand['offen'], 'id'));
        $this->assertSame(['zu_alt' => 1, 'kein_mitglied' => 3, 'ohne_datum' => 1], $stand['ausgelassen']);
        $this->assertSame([], $this->detailAbfragen);

        $stand = $dienst->weiter($nami, $stand, 2);
        $this->assertFalse(ProKopfNami::fertig($stand));
        $stand = $dienst->weiter($nami, $stand, 2);
        $this->assertTrue(ProKopfNami::fertig($stand));
        $this->assertSame([101, 102, 103], $this->detailAbfragen);

        $personen = ProKopfNami::personen($stand);
        $this->assertSame(['Auer', 'Jung', 'Muster'], array_column($personen, 'nachname'));
        $this->assertSame(['Hauptstraße 1', '66740', 'Saarlouis'], [$personen[2]['strasse'], $personen[2]['plz'], $personen[2]['ort']]);
        $this->assertSame('2015-05-04', $personen[2]['geburtsdatum']->toDateString());
    }

    #[TestDox('Seite: „Aus NaMi abrufen“ zeigt den Fortschritt, fragt weiter und liefert das PDF; danach ist der Zwischenstand weg')]
    public function test_seite_in_etappen(): void
    {
        $this->actingAs($kasse = $this->kasse());

        $seite = Livewire::test(ProKopfZuschussPage::class)
            ->assertActionVisible('nami')
            ->callAction('nami', ['jahr' => '2027'])
            ->assertSet('fortschritt', '2 von 3 Anschriften aus NaMi geholt …')
            ->assertSee('Abruf aus NaMi läuft');

        // Zwischenstand nur verschlüsselt im Cache, ohne Klartext-Namen.
        $roh = (string) Cache::get('pro-kopf-nami:'.$kasse->id);
        $this->assertNotSame('', $roh);
        $this->assertStringNotContainsString('Muster', $roh);

        $seite->call('weiter')
            ->assertFileDownloaded('pro-kopf-zuschuss-2027.pdf')
            ->assertSet('fortschritt', null)
            ->assertNotified('3 Jugendliche in der Liste – die Stadt verlangt mindestens 5');

        $this->assertNull(Cache::get('pro-kopf-nami:'.$kasse->id));
        // Nichts dauerhaft gespeichert (z. B. nicht in der Beitragskasse).
        $this->assertSame(0, BeitragsMitglied::query()->count());
    }

    #[TestDox('Verweigert NaMi einzelne Details, kommt die Person ohne Anschrift auf die Liste, mit Hinweis')]
    public function test_ohne_anschrift(): void
    {
        $this->details[102] = null;
        config(['mitglieder.per_run' => 10]);
        $this->actingAs($this->kasse());

        Livewire::test(ProKopfZuschussPage::class)
            ->callAction('nami', ['jahr' => '2027'])
            ->assertFileDownloaded('pro-kopf-zuschuss-2027.pdf');

        $stand = (new ProKopfNami)->weiter(new NamiVerzeichnis, (new ProKopfNami)->beginnen(new NamiVerzeichnis, '210601', Carbon::create(2027, 1, 1)), 10);
        $this->assertSame(1, $stand['ohne_anschrift']);
        $this->assertSame('', collect($stand['personen'])->firstWhere('nachname', 'Jung')['strasse']);
    }

    #[TestDox('Persönliche Anmeldung: mit eigener Mitgliedsnummer und Passwort, Sitzung reicht für alle Etappen; Passwort wird nicht gespeichert')]
    public function test_persoenliche_anmeldung(): void
    {
        config(['mitglieder.nami.auth' => 'persoenlich', 'mitglieder.nami.user' => null, 'mitglieder.nami.password' => null]);
        $this->actingAs($kasse = $this->kasse());
        $anmeldungen = 0;
        Http::fake(['*/auth/manual/sessionStartup' => function (Request $request) use (&$anmeldungen) {
            $anmeldungen++;
            $this->assertSame(['username' => '654321', 'password' => 'meins', 'Login' => 'API'], $request->data());

            return Http::response(['statusCode' => 0], 200, ['Set-Cookie' => 'JSESSIONID=xyz; Path=/ica']);
        }]);

        Livewire::test(ProKopfZuschussPage::class)
            ->callAction('nami', ['jahr' => '2027', 'nami_user' => '654321', 'nami_password' => 'meins'])
            ->assertHasNoActionErrors()
            ->assertSet('fortschritt', '2 von 3 Anschriften aus NaMi geholt …')
            ->call('weiter')
            ->assertFileDownloaded('pro-kopf-zuschuss-2027.pdf');

        $this->assertSame(1, $anmeldungen);
        $this->assertFalse($kasse->fresh()->hasNamiZugang());
    }

    #[TestDox('NaMi gestört: klare Meldung, kein Fortschritt; ohne NaMi-Anbindung gibt es den Knopf nicht')]
    public function test_fehler_und_ohne_nami(): void
    {
        $this->listeKaputt = true;
        $this->actingAs($this->kasse());

        Livewire::test(ProKopfZuschussPage::class)
            ->callAction('nami', ['jahr' => '2027'])
            ->assertNotified('NaMi-Abruf fehlgeschlagen')
            ->assertSet('fortschritt', null);

        config(['mitglieder.driver' => 'off']);
        Livewire::test(ProKopfZuschussPage::class)->assertActionHidden('nami')->assertSee('Dazu den Mitglieder-Export aus NaMi hochladen.');
    }

    #[TestDox('„Weiter“ ohne Zwischenstand (z. B. nach 30 Minuten): Hinweis, neu zu starten')]
    public function test_abgelaufen(): void
    {
        $this->actingAs($this->kasse());

        Livewire::test(ProKopfZuschussPage::class)
            ->call('weiter')
            ->assertNotified('Der Abruf ist abgelaufen. Bitte neu starten.');
    }
}
