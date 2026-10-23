<?php

namespace Tests\Feature\Sammelbestellung;

use App\Filament\Resources\Shop\BestellungResource;
use App\Filament\Resources\Shop\Pages\ListArtikel;
use App\Filament\Resources\Shop\Pages\ManageRunden;
use App\Filament\Resources\Shop\Pages\ManageSets;
use App\Filament\Resources\Shop\RundeResource;
use App\Filament\Resources\Shop\SetResource;
use App\Mail\ShopBestellungMail;
use App\Mail\ShopRundeMail;
use App\Mail\ZahlungsaufforderungMail;
use App\Models\BeitragsMitglied;
use App\Models\Shop\Artikel;
use App\Models\Shop\Bestellung;
use App\Models\Shop\Position;
use App\Models\Shop\Runde;
use App\Models\Shop\Set;
use App\Models\User;
use App\Models\Zahlung;
use App\Services\PrivacyRequests;
use App\Services\Sammelbestellung\Ausgabe;
use App\Support\Features;
use App\Support\Sammelbestellung;
use App\Support\SammelbestellungAblauf;
use App\Support\Zahlungen;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Sammelbestellung Phase 2: Sets, Schließen mit Storno, Erinnerung,
 * „Ware ist da“, Rechnung, Ausgabe, Mitgliedsprüfung, Löschfristen.
 */
class SammelbestellungPhase2Test extends TestCase
{
    use RefreshDatabase;

    private Runde $runde;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dpsg.site.level' => 'Stamm']);
        Features::setzen(Features::ZAHLUNGEN, true);
        Features::setzen(Features::SAMMELBESTELLUNG, true);
        Features::setzen(Features::SAMMELBESTELLUNG_FUER_ALLE, true);
        Zahlungen::kontoSetzen('DPSG Stamm Test', 'DE89 3704 0044 0532 0130 00', null, null);
        Mail::fake();
        $this->runde = Runde::query()->create(['name' => 'Herbst', 'bestelldatum' => now()->addDay()->toDateString(), 'status' => 'offen']);
    }

    /** @param array<string, int> $groessen Bezeichnung => Preis in Cent */
    private function artikel(string $name, string $nummer, array $groessen): Artikel
    {
        $artikel = Artikel::query()->create(['nummer' => $nummer, 'name' => $name, 'preis_cent' => min($groessen), 'freigegeben' => true]);
        $i = 0;
        foreach ($groessen as $bezeichnung => $preis) {
            $artikel->varianten()->create(['nummer' => $nummer.'.'.$i++, 'bezeichnung' => $bezeichnung ?: null, 'preis_cent' => $preis, 'aktiv' => true]);
        }

        return $artikel->load('varianten');
    }

    private function starterset(): Set
    {
        $hemd = $this->artikel('Klufthemd', '0100003', ['Kragenweite 30' => 4800, 'Kragenweite 31/32' => 4900]);
        $aufnaeher = $this->artikel('Basisaufnäher', '0400010', ['' => 600]);
        $knoten = $this->artikel('Halstuchknoten', '0300020', ['' => 350]);
        $halstuch = $this->artikel('Halstuch', '0300001', ['' => 1500]);
        $set = Set::query()->create(['name' => 'Starterset', 'freigegeben' => true, 'bild_artikel_id' => $hemd->id]);
        $set->teile()->createMany([
            ['artikel_id' => $hemd->id, 'menge' => 1, 'sort' => 1],
            ['artikel_id' => $aufnaeher->id, 'menge' => 2, 'sort' => 2],
            ['artikel_id' => $knoten->id, 'menge' => 1, 'sort' => 3],
            ['artikel_id' => $halstuch->id, 'menge' => 1, 'optional' => true, 'vorgewaehlt' => false, 'sort' => 4],
        ]);

        return $set->load('teile.artikel.varianten');
    }

    private function bestellen(array $pos, string $email = 'erika@example.org'): Bestellung
    {
        $this->post('/bestellen', [
            '_t' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
            'name' => 'Erika Muster', 'email' => $email, 'rolle' => 'erziehungsberechtigt', 'einwilligung' => '1', 'pos' => $pos,
        ])->assertRedirect(route('shop.danke'));
        $bestellung = Bestellung::query()->latest('id')->first();
        $bestellung->update(['bestaetigt_am' => now()]);
        Sammelbestellung::zahlungAnfordern($bestellung);

        return $bestellung->fresh();
    }

    private function login(string $role): User
    {
        $user = User::query()->create(['name' => $role, 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    #[TestDox('Set: Angebot zeigt es, Bestellung legt je Teil eine Position an (Pflichtteile, Größe, optionales Teil nur angehakt)')]
    public function test_set_bestellen(): void
    {
        $set = $this->starterset();
        [$hemd, $aufnaeher, $knoten, $halstuch] = $set->teile->all();
        $this->assertSame(4800 + 1200 + 350, Sammelbestellung::setPreisAb($set));
        $this->get('/bestellen')->assertOk()->assertSee('Starterset')->assertSee('Halstuch')->assertSee('(optional)', false);

        // Ohne Größe für das Hemd: Fehler.
        $this->post('/bestellen', [
            '_t' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
            'name' => 'Erika', 'email' => 'e@example.org', 'rolle' => 'mitglied', 'einwilligung' => '1',
            'pos' => [['mitglied' => 'Mia Muster', 'menge' => 1, 'set' => $set->id, 'teile' => []]],
        ])->assertSessionHasErrors('pos.0.set');

        $bestellung = $this->bestellen([['mitglied' => 'Mia Muster', 'stufe' => 'woelflinge', 'menge' => 1, 'set' => $set->id,
            'teile' => [$hemd->id => ['variante' => $hemd->artikel->varianten[1]->id], $halstuch->id => ['dabei' => '0']]]]);
        $this->assertSame([['Klufthemd', 'Kragenweite 31/32', 1], ['Basisaufnäher', null, 2], ['Halstuchknoten', null, 1]],
            $bestellung->positionen->map(fn (Position $p) => [$p->bezeichnung, $p->groesse, $p->menge])->all());
        $this->assertCount(1, $bestellung->positionen->pluck('set_gruppe')->unique());
        $this->assertSame(4900 + 1200 + 350, Zahlung::query()->sole()->betrag_cent);

        // Mit Halstuch und zwei Sets.
        $zwei = $this->bestellen([['mitglied' => 'Tom Muster', 'menge' => 2, 'set' => $set->id,
            'teile' => [$hemd->id => ['variante' => $hemd->artikel->varianten[0]->id], $halstuch->id => ['dabei' => '1']]]], 'tom@example.org');
        $this->assertSame([2, 4, 2, 2], $zwei->positionen->pluck('menge')->all());

        // Pflichtteil nicht mehr im Shop: Set nicht bestellbar, Hinweis im Backend.
        $knoten->artikel->update(['freigegeben' => false]);
        $this->assertFalse($set->fresh()->load('teile.artikel.varianten')->bestellbar());
        $this->get('/bestellen')->assertDontSee('Starterset');
        $this->login('sammelbestellung');
        $this->get(SetResource::getUrl())->assertOk()->assertSee('nicht bestellbar');
    }

    #[TestDox('Runde: Erinnerung am Vortag, Hinweis am Bestelldatum, Schließen storniert Unbezahltes (außer gehalten), Ware ist da, Abrechnung')]
    public function test_rundenablauf(): void
    {
        $hemd = $this->artikel('Klufthemd', '0100003', ['Kragenweite 30' => 4800]);
        $tuch = $this->artikel('Halstuch', '0300001', ['' => 1500]);
        $bezahlt = $this->bestellen([['mitglied' => 'Mia Muster', 'menge' => 1, 'variante' => $hemd->varianten[0]->id]], 'mia@example.org');
        $offen = $this->bestellen([['mitglied' => 'Tom Muster', 'menge' => 1, 'variante' => $tuch->varianten[0]->id]], 'tom@example.org');
        $gehalten = $this->bestellen([['mitglied' => 'Ida Muster', 'menge' => 1, 'variante' => $tuch->varianten[0]->id]], 'ida@example.org');
        $gehalten->update(['gehalten' => true]);
        $bezahlt->zahlungen()->sole()->eingang(4800, now());

        // Teilweise: Hemd bezahlt, Nachforderung fürs Halstuch offen.
        $teil = $this->bestellen([['mitglied' => 'Ben Muster', 'menge' => 1, 'variante' => $hemd->varianten[0]->id]], 'ben@example.org');
        $teil->zahlungen()->sole()->eingang(4800, now());
        $teil->positionen()->create(['artikel_id' => $tuch->id, 'nummer' => 'x', 'bezeichnung' => 'Halstuch', 'menge' => 1, 'preis_cent' => 1500, 'mitglied' => 'Ben Muster', 'frei' => false, 'bestaetigt' => true]);
        Sammelbestellung::zahlungAnfordern($teil);

        Mail::fake();
        $sammler = User::query()->create(['name' => 'Michele', 'email' => 'michele@example.org', 'status' => 'active']);
        $sammler->roleAssignments()->create(['role' => 'sammelbestellung', 'scope_type' => 'global']);
        $this->artisan('sammelbestellung:taeglich')->assertSuccessful();
        Mail::assertQueued(ZahlungsaufforderungMail::class, 3);
        $this->assertNotNull($offen->fresh()->erinnert_am);
        $this->assertSame(0, SammelbestellungAblauf::erinnern($this->runde), 'nur einmal');
        Mail::assertNotQueued(ShopRundeMail::class);

        $this->travel(1)->days();
        $this->artisan('sammelbestellung:taeglich')->assertSuccessful();
        Mail::assertQueued(ShopRundeMail::class, fn ($m) => $m->hasTo('michele@example.org'));

        Mail::fake();
        $this->assertSame(2, SammelbestellungAblauf::schliessen($this->runde));
        $this->assertSame('geschlossen', $this->runde->fresh()->status);
        $this->assertNotNull($offen->fresh()->storniert_am);
        $this->assertNull($gehalten->fresh()->storniert_am);
        $this->assertSame(['Klufthemd'], $teil->fresh()->positionen->pluck('bezeichnung')->all());
        $this->assertSame(['storniert'], $offen->zahlungen()->pluck('status')->all());
        Mail::assertQueued(ShopBestellungMail::class, fn ($m) => $m->art === 'storno' && $m->hasTo('tom@example.org'));
        Mail::assertQueued(ShopBestellungMail::class, fn ($m) => $m->art === 'storno' && $m->hasTo('ben@example.org') && str_contains((string) $m->hinweis, 'Halstuch'));
        $this->assertSame(2, Sammelbestellung::sammelliste($this->runde)->sole()['menge']);

        SammelbestellungAblauf::bestellt($this->runde);
        $this->assertSame('bestellt', $bezahlt->fresh()->stand());
        Mail::fake();
        $this->assertSame(2, SammelbestellungAblauf::geliefert($this->runde, 'Ausgabe am Freitag.'));
        Mail::assertQueued(ShopBestellungMail::class, fn ($m) => $m->art === 'abholbereit' && $m->hasTo('mia@example.org'));
        $this->assertSame('abholbereit', $bezahlt->fresh()->stand());

        $this->runde->update(['rechnung_cent' => 8640, 'versand_cent' => 590]);
        $this->assertSame(['eingang' => 9600, 'rechnung' => 8640, 'versand' => 590, 'differenz' => 370], SammelbestellungAblauf::abrechnung($this->runde->fresh()));
    }

    #[TestDox('Ausgabe: PDF-Liste und Scheine, Abhaken auch durch die Verwaltung, Stand „ausgegeben“')]
    public function test_ausgabe(): void
    {
        $hemd = $this->artikel('Klufthemd', '0100003', ['Kragenweite 30' => 4800]);
        $b = $this->bestellen([['mitglied' => 'Mia Muster', 'stufe' => 'woelflinge', 'menge' => 1, 'variante' => $hemd->varianten[0]->id]]);
        $b->zahlungen()->sole()->eingang(4800, now());
        SammelbestellungAblauf::schliessen($this->runde);

        $gruppen = SammelbestellungAblauf::ausgabeliste($this->runde);
        $this->assertSame([config('dpsg.stages.woelflinge.label')], $gruppen->keys()->all());
        $this->assertStringStartsWith('%PDF', app(Ausgabe::class)->pdf($this->runde, 'liste'));
        $this->assertStringStartsWith('%PDF', app(Ausgabe::class)->pdf($this->runde, 'scheine', $b));

        $user = $this->login('verwaltung');
        $this->get(RundeResource::getUrl())->assertOk();
        $this->get(BestellungResource::getUrl('edit', ['record' => $b]))->assertOk();
        SammelbestellungAblauf::ausgeben($b->positionen->first(), $user, 'Liste vom 12.12.');
        $this->assertSame('ausgegeben', $b->fresh()->stand());
        $this->assertSame('verwaltung', $b->positionen()->first()->ausgeber->name);

        $this->login('kasse');
        $this->get(RundeResource::getUrl())->assertForbidden();
    }

    #[TestDox('Mitgliedsprüfung gegen die Mitgliederliste der Kasse; ohne Liste kein Ergebnis')]
    public function test_mitgliedschaft(): void
    {
        $hemd = $this->artikel('Klufthemd', '0100003', ['Kragenweite 30' => 4800]);
        $b = $this->bestellen([['mitglied' => 'Mia Müller', 'menge' => 1, 'variante' => $hemd->varianten[0]->id]]);
        $this->assertNull(SammelbestellungAblauf::mitgliedschaftPruefen($b));

        BeitragsMitglied::query()->create(['nami_id' => 1, 'vorname' => 'Mia Sophie', 'nachname' => 'Mueller', 'aktiv' => true]);
        $this->assertSame('ja', SammelbestellungAblauf::mitgliedschaftPruefen($b));
        $b->positionen()->create(['bezeichnung' => 'x', 'menge' => 1, 'mitglied' => 'Tom Fremd', 'frei' => false, 'bestaetigt' => true]);
        $this->assertSame('nein', SammelbestellungAblauf::mitgliedschaftPruefen($b->fresh()));
        $b->update(['mitgliedschaft' => 'bestaetigt']);
        $this->assertSame('bestaetigt', SammelbestellungAblauf::mitgliedschaftPruefen($b->fresh()));
    }

    #[TestDox('Löschfristen: unbestätigt nach 48 Stunden weg, erledigte Runde nach Frist anonymisiert; Datenschutz-Anfrage anonymisiert')]
    public function test_loeschfristen(): void
    {
        $hemd = $this->artikel('Klufthemd', '0100003', ['Kragenweite 30' => 4800]);
        $b = $this->bestellen([['mitglied' => 'Mia Muster', 'menge' => 1, 'variante' => $hemd->varianten[0]->id]]);
        $b->zahlungen()->sole()->eingang(4800, now());
        $this->post('/bestellen', [
            '_t' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
            'name' => 'Nie Bestätigt', 'email' => 'nie@example.org', 'rolle' => 'mitglied', 'einwilligung' => '1',
            'pos' => [['mitglied' => 'Nie Bestätigt', 'menge' => 1, 'variante' => $hemd->varianten[0]->id]],
        ]);
        $this->assertSame(2, Bestellung::query()->count());

        $this->travel(49)->hours();
        $this->assertSame(['unbestaetigt' => 1, 'anonymisiert' => 0], SammelbestellungAblauf::aufraeumen());

        $this->runde->update(['status' => 'erledigt', 'erledigt_am' => now()]);
        $this->travel(SammelbestellungAblauf::loeschfristMonate())->months();
        $this->travel(1)->days();
        $this->assertSame(['unbestaetigt' => 0, 'anonymisiert' => 1], SammelbestellungAblauf::aufraeumen());
        $b->refresh();
        $this->assertSame(['gelöscht', 'geloescht@invalid', 'gelöscht'], [$b->name, $b->email, $b->positionen->first()->mitglied]);
        $this->assertSame(4800, (int) Zahlung::query()->sole()->bezahlt_cent);

        // Datenschutz-Anfrage: Auskunft enthält die Bestellung, Löschen anonymisiert.
        Runde::query()->create(['name' => 'Frühjahr', 'bestelldatum' => now()->addWeek()->toDateString(), 'status' => 'offen']);
        $neu = $this->bestellen([['mitglied' => 'Ida Muster', 'menge' => 1, 'variante' => $hemd->varianten[0]->id]], 'Ida@Example.org');
        $requests = app(PrivacyRequests::class);
        $this->assertCount(1, $requests->find('ida@example.org')['bestellungen']);
        $zip = new \ZipArchive;
        $zip->open($requests->export('ida@example.org'));
        $this->assertStringContainsString('Ida Muster', (string) $zip->getFromName('auskunft.json'));
        $this->assertSame(1, $requests->delete('ida@example.org')['bestellungen']);
        $this->assertNotNull($neu->fresh()->anonymisiert_am);
    }

    #[TestDox('Backend: Set anlegen, Runde schließen und Ausgabeliste über die Aktionen')]
    public function test_backend_aktionen(): void
    {
        $hemd = $this->artikel('Klufthemd', '0100003', ['Kragenweite 30' => 4800]);
        $knoten = $this->artikel('Halstuchknoten', '0300020', ['' => 350]);
        $this->login('sammelbestellung');

        Livewire::test(ManageSets::class)->callAction('create', [
            'name' => 'Starterset', 'freigegeben' => true, 'bild_artikel_id' => $hemd->id,
            'teile' => [['artikel_id' => $hemd->id, 'menge' => 1], ['artikel_id' => $knoten->id, 'menge' => 1, 'optional' => true, 'vorgewaehlt' => false]],
        ])->assertHasNoActionErrors();
        $set = Set::query()->with('teile')->sole();
        $this->assertSame([[$hemd->id, false], [$knoten->id, true]], $set->teile->map(fn ($t) => [$t->artikel_id, $t->optional])->all());

        $b = $this->bestellen([['mitglied' => 'Mia Muster', 'menge' => 1, 'variante' => $hemd->varianten[0]->id]]);
        $b->zahlungen()->sole()->eingang(4800, now());
        Livewire::test(ManageRunden::class)->callTableAction('schliessen', $this->runde)->assertHasNoTableActionErrors();
        $this->assertSame('geschlossen', $this->runde->fresh()->status);
        Livewire::test(ManageRunden::class)->callTableAction('ausgabeliste', $this->runde)->assertFileDownloaded('ausgabeliste-herbst.pdf');
        Livewire::test(ManageRunden::class)->callTableAction('rechnung', $this->runde, ['rechnung_cent' => '43,20', 'versand_cent' => '5,90'])->assertHasNoTableActionErrors();
        $this->assertSame([4320, 590], [$this->runde->fresh()->rechnung_cent, $this->runde->fresh()->versand_cent]);
    }

    #[TestDox('Eigene Artikel: im Backend anlegen, bestellbar, nicht in der Rüsthaus-Sammelliste, eigene Liste')]
    public function test_eigene_artikel(): void
    {
        $hemd = $this->artikel('Klufthemd', '0100003', ['Kragenweite 30' => 4800]);
        $this->login('sammelbestellung');
        Livewire::test(ListArtikel::class)->callAction('create', [
            'name' => 'Stammesaufnäher', 'kategorie' => 'Aufnäher', 'freigegeben' => true,
            'varianten' => [['bezeichnung' => '', 'preis_cent' => '4,00', 'aktiv' => true]],
        ])->assertHasNoActionErrors();
        $eigen = Artikel::query()->where('eigen', true)->with('varianten')->sole();
        $this->assertSame([400, 1], [$eigen->preis_cent, $eigen->varianten->count()]);
        $this->assertStringStartsWith('STAMM-', $eigen->nummer);
        $this->get('/bestellen')->assertOk()->assertSee('Stammesaufnäher')->assertSee('vom Stamm');

        $b = $this->bestellen([
            ['mitglied' => 'Mia Muster', 'menge' => 1, 'variante' => $hemd->varianten[0]->id],
            ['mitglied' => 'Mia Muster', 'menge' => 2, 'variante' => $eigen->varianten[0]->id],
        ]);
        $this->assertSame(4800 + 800, Zahlung::query()->sole()->betrag_cent);
        $b->zahlungen()->sole()->eingang(5600, now());
        $this->assertSame(['Klufthemd'], Sammelbestellung::sammelliste($this->runde)->pluck('bezeichnung')->all());
        $this->assertSame([['Stammesaufnäher', 2]], Sammelbestellung::sammelliste($this->runde, true)->map(fn ($z) => [$z['bezeichnung'], $z['menge']])->all());
    }

    #[TestDox('Ohne Mitgliedsnamen gilt die Bestellung für die bestellende Person selbst')]
    public function test_ohne_mitglied_selbst(): void
    {
        $hemd = $this->artikel('Klufthemd', '0100003', ['Kragenweite 30' => 4800]);
        $b = $this->bestellen([['mitglied' => '', 'menge' => 1, 'variante' => $hemd->varianten[0]->id]]);
        $this->assertSame('Erika Muster', $b->positionen->first()->mitglied);
    }
}
