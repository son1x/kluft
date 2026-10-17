<?php

namespace Tests\Feature\Mitglieder;

use App\Filament\Pages\NamiTestabfrage;
use App\Filament\Resources\Beitraege\BeitragResource;
use App\Filament\Resources\Beitraege\Pages\ListBeitraege;
use App\Filament\Resources\BeitragsAbrechnungen\BeitragsAbrechnungResource;
use App\Filament\Resources\BeitragsAbrechnungen\Pages\ManageBeitragsAbrechnungen;
use App\Jobs\BeitraegeAbrufen;
use App\Models\Beitrag;
use App\Models\BeitragsAbrechnung;
use App\Models\BeitragsMitglied;
use App\Models\User;
use App\Services\Beitraege\BeitragsAbruf;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\Beitraege;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Mitgliedsbeiträge eines Stamms: Abruf aus NaMi, Halbjahre, Zahlungen von
 * Hand, Zugriff nur für die Kasse (docs/beitraege.md).
 */
class BeitraegeTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $liste = [];

    /** @var array<int, array<string, mixed>> */
    private array $details = [];

    /** @var list<int> */
    private array $detailAbfragen = [];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        config(['mitglieder.driver' => 'nami', 'mitglieder.nami.auth' => 'konto', 'mitglieder.nami.user' => '123456', 'mitglieder.nami.password' => 'geheim',
            'dpsg.einrichtung.nummer' => '210601']);
        Features::setzen(Features::MITGLIEDSBEITRAEGE, true);
        Beitraege::saetzeSetzen(['Voller Beitrag' => ['bund' => 1700, 'stamm' => 2500], 'Familienermäßigt' => ['bund' => 1100, 'stamm' => 1700]]);

        $this->liste = [
            ['id' => 101, 'entries_vorname' => 'Mia', 'entries_nachname' => 'Muster', 'entries_mitgliedsNummer' => 4711, 'entries_geburtsDatum' => '2010-03-03 00:00:00', 'entries_email' => 'mia@example.org', 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied', 'entries_version' => 3],
            ['id' => 102, 'entries_vorname' => 'Ben', 'entries_nachname' => 'Muster', 'entries_mitgliedsNummer' => 4712, 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied', 'entries_version' => 1],
            ['id' => 103, 'entries_vorname' => 'Sam', 'entries_nachname' => 'Schnupper', 'entries_mitgliedsNummer' => 4713, 'entries_status' => 'Aktiv', 'entries_mglType' => 'Schnuppermitglied', 'entries_version' => 1],
            ['id' => 104, 'entries_vorname' => 'Ella', 'entries_nachname' => 'Elternbeirat', 'entries_mitgliedsNummer' => 4714, 'entries_status' => 'Aktiv', 'entries_mglType' => 'Nicht-Mitglied', 'entries_version' => 1],
            ['id' => 105, 'entries_vorname' => 'Neo', 'entries_nachname' => 'Neu', 'entries_mitgliedsNummer' => 4715, 'entries_status' => 'Aktiv', 'entries_mglType' => 'Mitglied', 'entries_version' => 1],
        ];
        $this->details = [
            101 => ['beitragsartId' => 6, 'eintrittsdatum' => '2019-04-01 00:00:00', 'austrittsDatum' => '', 'vorname' => 'Mia', 'strasse' => 'Musterweg 1', 'mitgliedsNummer' => 4711],
            102 => ['beitragsartId' => 2, 'eintrittsdatum' => '2021-09-01 00:00:00', 'austrittsDatum' => null],
            105 => ['beitragsartId' => 6, 'eintrittsdatum' => '2027-03-01 00:00:00', 'austrittsDatum' => null],
        ];

        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_ends_with($url, '/auth/manual/sessionStartup')) {
                return Http::response(['statusCode' => 0], 200, ['Set-Cookie' => 'JSESSIONID=abc; Path=/ica']);
            }
            if (preg_match('#/gruppierung/node/(\d+)#', $url, $m)) {
                return Http::response(['success' => true, 'data' => match ((int) $m[1]) {
                    1 => [['id' => 10, 'descriptor' => 'Diözesanverband Trier 210000'], ['id' => 11, 'descriptor' => 'Diözesanverband Köln 040000']],
                    10 => [['id' => 20, 'descriptor' => 'Bezirk Saar-Hochwald 210600'], ['id' => 21, 'descriptor' => 'Bezirk Trier 210200']],
                    20 => [['id' => 30, 'descriptor' => 'Graf Folke Bernadotte 210601']],
                    default => $this->fail('Nur Diözese und Bezirk des Stamms durchsuchen, nicht '.$m[1]),
                }]);
            }
            if (str_contains($url, '/gruppierung/gruppierung/30/flist')) {
                return Http::response(['success' => true, 'data' => $this->liste]);
            }
            if (preg_match('#/gruppierung/gruppierung/30/(\d+)$#', $url, $m)) {
                $this->detailAbfragen[] = (int) $m[1];

                return Http::response(['success' => true, 'data' => $this->details[(int) $m[1]] ?? []]);
            }
            if (str_contains($url, '/namiBeitrag/beitragsartmgl/gruppierung/30')) {
                return Http::response(['success' => true, 'data' => [['id' => 6, 'descriptor' => 'Voller Beitrag (VOLLER BEITRAG - VERBANDSBEITRAG)']]]);
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

    #[TestDox('Halbjahre wie die Rechnung des Bundesverbands: Januar–Juni und Juli–Dezember')]
    public function test_halbjahre(): void
    {
        $this->assertSame('2026-2', Beitraege::aktuell(Carbon::parse('2026-07-01')));
        $this->assertSame('2027-1', Beitraege::aktuell(Carbon::parse('2027-06-30')));
        $this->assertSame('2. Halbjahr 2026', Beitraege::label('2026-2'));
        [$beginn, $ende] = Beitraege::zeitraum('2026-2');
        $this->assertSame(['2026-07-01', '2026-12-31'], [$beginn->toDateString(), $ende->toDateString()]);
        $this->assertFalse(Beitraege::gueltig('2026-3'));
    }

    #[TestDox('Abruf aus NaMi: nur Mitglieder (keine Schnupper- und Nicht-Mitglieder), Beitragsart aus den Details, Beiträge je Halbjahr nach Beitragssatz')]
    public function test_abruf_und_halbjahr(): void
    {
        $abruf = new BeitragsAbruf(new NamiVerzeichnis);
        $ergebnis = $abruf->abrufen('21 06 01');

        $this->assertSame(['mitglieder' => 3, 'neu' => 3, 'details' => 3, 'offen' => 0, 'ausgetreten' => 0], $ergebnis);
        $this->assertSame(['Muster, Ben', 'Muster, Mia', 'Neu, Neo'], BeitragsMitglied::query()->orderBy('nachname')->orderBy('vorname')->get()->map->name()->all());
        $mia = BeitragsMitglied::query()->where('nami_id', 101)->firstOrFail();
        $this->assertSame(['Voller Beitrag', '4711', '2019-04-01'], [$mia->beitragsart, $mia->mitgliedsnummer, $mia->eintritt?->toDateString()]);
        $this->assertSame('Familienermäßigt', BeitragsMitglied::query()->where('nami_id', 102)->value('beitragsart'));

        // Neo tritt erst 2027 ein: im 2. Halbjahr 2026 kein Beitrag.
        $this->assertSame(['neu' => 2, 'angepasst' => 0], $abruf->halbjahrAnlegen('2026-2'));
        $this->assertSame(7000, (int) Beitrag::query()->where('halbjahr', '2026-2')->sum('soll_cent'));
        $this->assertSame(2800, (int) Beitrag::query()->where('halbjahr', '2026-2')->sum('bundes_cent'));

        // Zweiter Abruf: unveränderte Versionen ohne Detailabfrage; wer fehlt, ist nicht mehr aktiv.
        $this->detailAbfragen = [];
        $this->liste[0]['entries_version'] = 4;
        $this->details[101]['beitragsartId'] = 2;
        unset($this->liste[1]);
        $this->liste = array_values($this->liste);
        $ergebnis = $abruf->abrufen('210601');
        $this->assertSame([101], $this->detailAbfragen);
        $this->assertSame(1, $ergebnis['ausgetreten']);
        $this->assertFalse(BeitragsMitglied::query()->where('nami_id', 102)->value('aktiv'));

        // Offener Beitrag folgt der neuen Beitragsart; Bens Beitrag bleibt (er war im Halbjahr Mitglied).
        $this->assertSame(['neu' => 0, 'angepasst' => 1], $abruf->halbjahrAnlegen('2026-2'));
        $this->assertSame(2800, Beitrag::query()->whereRelation('mitglied', 'nami_id', 101)->value('soll_cent'));
        $this->assertSame(2, Beitrag::query()->where('halbjahr', '2026-2')->count());
    }

    #[TestDox('Bezahlte Beiträge bleiben bei geänderter Beitragsart, wie sie sind; erlassen zählt als bezahlt')]
    public function test_bezahlt_bleibt(): void
    {
        $abruf = new BeitragsAbruf(new NamiVerzeichnis);
        $abruf->abrufen('210601');
        $abruf->halbjahrAnlegen('2026-2');
        $mia = Beitrag::query()->whereRelation('mitglied', 'nami_id', 101)->firstOrFail();
        $mia->update(['bezahlt_cent' => 4200, 'bezahlt_am' => '2026-08-01', 'zahlart' => 'lastschrift']);
        $ben = Beitrag::query()->whereRelation('mitglied', 'nami_id', 102)->firstOrFail();
        $ben->update(['zahlart' => 'erlassen']);

        Beitraege::saetzeSetzen(['Voller Beitrag' => ['bund' => 1800, 'stamm' => 3200], 'Familienermäßigt' => ['bund' => 1200, 'stamm' => 1800]]);
        $this->assertSame(['neu' => 0, 'angepasst' => 0], $abruf->halbjahrAnlegen('2026-2'));
        $this->assertSame(['bezahlt', 'bezahlt'], [$mia->fresh()->status(), $ben->fresh()->status()]);
        $this->assertSame(0, Beitrag::query()->offen()->count());

        $mia->update(['bezahlt_cent' => 2000]);
        $this->assertSame('teilweise', $mia->fresh()->status());
        $this->assertSame(1, Beitrag::query()->offen()->count());
    }

    #[TestDox('Abruf über die Warteschlange: verschlüsselt, je Lauf begrenzte Detailabfragen, danach Beiträge des Halbjahres')]
    public function test_job(): void
    {
        config(['mitglieder.per_run' => 1]);
        $this->assertInstanceOf(ShouldBeEncrypted::class, new BeitraegeAbrufen('210601', '2026-2'));

        BeitraegeAbrufen::dispatchSync('210601', '2026-2');

        $this->assertSame(3, BeitragsMitglied::query()->whereNotNull('beitragsart')->count());
        $this->assertSame(2, Beitrag::query()->where('halbjahr', '2026-2')->count());
        $this->assertSame('fertig', Beitraege::letzterAbruf()['status'] ?? null);
        $this->assertStringContainsString('3 Mitglieder aus NaMi', Beitraege::letzterAbruf()['text'] ?? '');
    }

    #[TestDox('Kasse → Mitgliedsbeiträge nur mit Funktion und Rolle „Kasse“ (oder Admin)')]
    public function test_zugriff(): void
    {
        $this->actingAs($this->user('verwaltung'));
        $this->assertFalse(BeitragResource::canViewAny());
        $this->get(BeitragResource::getUrl())->assertForbidden();

        $kasse = $this->user('kasse');
        $this->actingAs($kasse);
        $this->assertTrue(BeitragResource::canViewAny());
        $this->assertTrue(BeitragsAbrechnungResource::canViewAny());
        $this->get(BeitragResource::getUrl())->assertOk()->assertSee('Mitgliedsbeiträge');

        Features::setzen(Features::MITGLIEDSBEITRAEGE, false);
        $this->assertFalse(BeitragResource::canViewAny());
    }

    #[TestDox('Liste: Zahlung erfassen, mehrere als bezahlt markieren, nach offen filtern, Abruf starten')]
    public function test_liste(): void
    {
        $this->travelTo(Carbon::parse('2026-10-17 12:00'));
        $abruf = new BeitragsAbruf(new NamiVerzeichnis);
        $abruf->abrufen('210601');
        $abruf->halbjahrAnlegen('2026-2');
        $mia = Beitrag::query()->whereRelation('mitglied', 'nami_id', 101)->firstOrFail();
        $ben = Beitrag::query()->whereRelation('mitglied', 'nami_id', 102)->firstOrFail();
        $this->actingAs($this->user('kasse'));

        Livewire::test(ListBeitraege::class)
            ->assertCanSeeTableRecords([$mia, $ben])
            ->assertSee('Muster, Mia')->assertSee('70,00 €')
            ->callTableAction('zahlung', $mia, ['soll' => '42.00', 'bezahlt' => '42,00', 'bezahlt_am' => '2026-10-01', 'zahlart' => 'ueberweisung', 'notiz' => 'Kontoauszug 9'])
            ->assertHasNoTableActionErrors()
            ->filterTable('stand', 'offen')
            ->assertCanSeeTableRecords([$ben])
            ->assertCanNotSeeTableRecords([$mia]);

        $this->assertSame(['bezahlt', 4200, 'Kontoauszug 9'], [$mia->fresh()->status(), $mia->fresh()->bezahlt_cent, $mia->fresh()->notiz]);

        Livewire::test(ListBeitraege::class)
            ->callTableBulkAction('bezahlt', [$mia, $ben], ['bezahlt_am' => '2026-10-15', 'zahlart' => 'lastschrift']);
        $this->assertSame(['ueberweisung', 'lastschrift'], [$mia->fresh()->zahlart, $ben->fresh()->zahlart]);
        $this->assertSame(0, Beitrag::query()->offen()->count());

        Queue::fake();
        Livewire::test(ListBeitraege::class)
            ->callAction('abrufen', ['halbjahr' => '2027-1'])
            ->assertHasNoActionErrors();
        Queue::assertPushed(BeitraegeAbrufen::class, fn (BeitraegeAbrufen $job) => $job->nummer === '210601' && $job->halbjahr === '2027-1' && $job->namiSession === null);
    }

    #[TestDox('Beitragssätze aus Bundesbeitrag und Stammesanteil; Änderung passt offene Beiträge an; Rechnung neben Bundesbeitrag, Soll und Eingang')]
    public function test_saetze_und_abrechnung(): void
    {
        $abruf = new BeitragsAbruf(new NamiVerzeichnis);
        $abruf->abrufen('210601');
        $abruf->halbjahrAnlegen('2026-2');
        Beitrag::query()->whereRelation('mitglied', 'nami_id', 101)->update(['bezahlt_cent' => 4200, 'bezahlt_am' => '2026-08-01']);
        $this->actingAs($this->user('kasse'));

        $this->travelTo(Carbon::parse('2026-10-17 12:00'));
        $felder = Livewire::test(ListBeitraege::class)->mountAction('saetze')->get('mountedActions.0.data');
        $feld = collect($felder)->filter(fn ($wert, string $key) => str_ends_with($key, '_stamm') && $wert === '17,00')->keys()->first();
        $this->assertIsString($feld);
        $this->assertSame('11,00', $felder[str_replace('_stamm', '_bund', $feld)]);
        Livewire::test(ListBeitraege::class)->callAction('saetze', [...$felder, $feld => '19'])->assertHasNoActionErrors();
        $this->assertSame(['bund' => 1100, 'stamm' => 1900], Beitraege::saetze()['Familienermäßigt']);
        $this->assertSame([3000, 4200], Beitrag::query()->orderBy('soll_cent')->pluck('soll_cent')->all());

        Livewire::test(ManageBeitragsAbrechnungen::class)
            ->callAction('create', ['halbjahr' => '2026-2', 'mitglieder' => 3, 'rechnungsbetrag_cent' => '30,50', 'rechnungsdatum' => '2026-07-15'])
            ->assertHasNoActionErrors();
        $rechnung = BeitragsAbrechnung::query()->firstOrFail();
        $this->assertSame(3050, $rechnung->rechnungsbetrag_cent);
        Livewire::test(ManageBeitragsAbrechnungen::class)
            ->assertSee('2. Halbjahr 2026')->assertSee('30,50 €')->assertSee('in Kluft: 2 – abweichend')
            ->assertSee('28,00 €')->assertSee('abweichend von der Rechnung')->assertSee('72,00 €')->assertSee('1 offen');
    }

    #[TestDox('NaMi-Testabfrage: mit gespeichertem Zugang, Ergebnis ohne Namen, Nummern und Adressen')]
    public function test_testabfrage(): void
    {
        config(['mitglieder.nami.auth' => 'persoenlich']);
        $admin = $this->user('admin');
        $admin->forceFill(['nami_user' => '123456', 'nami_password' => 'geheim'])->save();
        $this->actingAs($admin->fresh());
        $this->assertTrue(NamiTestabfrage::canAccess());

        $bericht = (string) Livewire::test(NamiTestabfrage::class)
            ->assertSet('data.nummer', '210601')
            ->call('abfragen')
            ->assertHasNoErrors()
            ->get('bericht');

        $this->assertStringContainsString('NaMi-ID 30', $bericht);
        $this->assertStringContainsString('Diözesanverband Trier 210000', $bericht);
        $this->assertStringContainsString('Kluft würde 3 von 5 als beitragspflichtige Mitglieder übernehmen', $bericht);
        $this->assertStringContainsString('entries_mglType: "Mitglied" (lesbar)', $bericht);
        $this->assertStringContainsString('entries_geburtsDatum: "9999-99-99 99:99:99" (Text, 19 Zeichen)', $bericht);
        $this->assertStringContainsString('beitragsartId: 6 (lesbar)', $bericht);
        $this->assertStringContainsString('Voller Beitrag (VOLLER BEITRAG - VERBANDSBEITRAG)', $bericht);
        foreach (['Mia', 'Muster', '4711', 'mia@example', 'Musterweg', '123456', 'geheim'] as $geheim) {
            $this->assertStringNotContainsString($geheim, $bericht);
        }

        $this->actingAs($this->user('verwaltung'));
        $this->assertFalse(NamiTestabfrage::canAccess());
    }
}
