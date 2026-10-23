<?php

namespace Tests\Feature\Sammelbestellung;

use App\Filament\Resources\Shop\ArtikelResource;
use App\Filament\Resources\Shop\BestellungResource;
use App\Filament\Resources\Shop\Pages\ListArtikel;
use App\Filament\Resources\Shop\RundeResource;
use App\Mail\ShopBestellungMail;
use App\Mail\ZahlungsaufforderungMail;
use App\Models\Shop\Artikel;
use App\Models\Shop\Bestellung;
use App\Models\Shop\Runde;
use App\Models\Shop\Sperre;
use App\Models\User;
use App\Models\Zahlung;
use App\Services\Sammelbestellung\Ruesthaus;
use App\Support\Features;
use App\Support\Sammelbestellung;
use App\Support\Zahlungen;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Sammelbestellung Phase 1: Freigabe per Rüsthaus-Nummer, Sperrliste,
 * Bestellen mit Bestätigung, Zahlungsaufforderung, freie Artikel, Sammelliste.
 */
class SammelbestellungTest extends TestCase
{
    use RefreshDatabase;

    private const HEMD = 'https://www.ruesthaus.de/dpsg-artikel/pfadfinderkluft/hemden-blusen/2/halbarm-klufthemd';

    private const ZELT = 'https://www.ruesthaus.de/zelte/kohten/2371/komplett-kohte';

    /** Beispielseite im Aufbau der Rüsthaus-Produktseiten (Shopware 5). */
    private static function produkt(string $name, string $sku, string $preis, array $optionen = []): string
    {
        $select = $optionen === [] ? '' : '<select name="group[4]" class="x">'.implode('', array_map(
            fn ($v, $l) => '<option value="'.$v.'">'.$l.'</option>', array_keys($optionen), $optionen)).'</select>';

        return '<html><h1 class="product--title" itemprop="name"> '.$name.' </h1><meta itemprop="price" content="'.$preis.'"/>'
            .'<span itemprop="sku"> '.$sku.' </span><span class="image--element" data-img-large="https://www.ruesthaus.de/media/image/x.jpg"></span>'
            .$select.'<div class="product--description" itemprop="description"><p>Fair gehandelt.</p></div></html>';
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['dpsg.site.level' => 'Stamm', 'dpsg.sammelbestellung_pause_ms' => 0]);
        Features::setzen(Features::ZAHLUNGEN, true);
        Features::setzen(Features::SAMMELBESTELLUNG, true);
        Features::setzen(Features::SAMMELBESTELLUNG_FUER_ALLE, true);
        Zahlungen::kontoSetzen('DPSG Stamm Test', 'DE89 3704 0044 0532 0130 00', null, null);
        Mail::fake();

        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/search')) {
                $treffer = str_contains($url, '1404601') ? self::ZELT : self::HEMD;

                return Http::response('<div class="product--box"><a href="'.$treffer.'" class="product--title">x</a></div>');
            }
            if (str_starts_with($url, self::HEMD)) {
                return Http::response(match (true) {
                    str_contains($url, '=11') => self::produkt('Halbarm-Klufthemd', '0100003.32', '49.00', [10 => 'Kragenweite 30', 11 => 'Kragenweite 31/32']),
                    default => self::produkt('Halbarm-Klufthemd', '0100003.30', '48.00', [10 => 'Kragenweite 30', 11 => 'Kragenweite 31/32']),
                });
            }
            if (str_starts_with($url, self::ZELT)) {
                return Http::response(self::produkt('Komplett-Kohte', '1404601', '1071.60'));
            }
            if (str_ends_with($url, '/x.jpg')) {
                return Http::response('jpeg', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response('', 404);
        });
    }

    private function login(string $role): User
    {
        $user = User::query()->create(['name' => $role, 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    #[TestDox('Rüsthaus-Produktseite: Nummer, Name, Preis, Größen, Kategorie aus der Adresse')]
    public function test_parser(): void
    {
        $seite = Ruesthaus::produktseite(self::produkt('Halbarm-Klufthemd', '0100003.30', '48.00', [10 => 'Kragenweite 30']), self::HEMD);
        $this->assertSame(['0100003.30', 'Halbarm-Klufthemd', 4800, 'group[4]', 'Fair gehandelt.'], [$seite['nummer'], $seite['name'], $seite['preis_cent'], $seite['gruppe'], $seite['beschreibung']]);
        $this->assertSame('zelte', Ruesthaus::kategorie(self::ZELT));
        $this->assertNull(Ruesthaus::produktseite('<html>keine Produktseite</html>', ''));
    }

    #[TestDox('Freigabe per Nummer: Artikel mit Größen und Bild; Sperre nach Kategorie greift')]
    public function test_freigabe_und_sperre(): void
    {
        $artikel = Sammelbestellung::freigeben('0100003.32');
        $this->assertSame(['0100003.30', true, 'dpsg-artikel'], [$artikel->nummer, $artikel->freigegeben, $artikel->rh_kategorie]);
        $this->assertSame([['0100003.30', 'Kragenweite 30', 4800], ['0100003.32', 'Kragenweite 31/32', 4900]],
            $artikel->varianten->map(fn ($v) => [$v->nummer, $v->bezeichnung, $v->preis_cent])->all());
        $this->assertSame('Rüsthaus', $artikel->bild?->credit);

        Sperre::query()->create(['typ' => 'kategorie', 'wert' => 'zelte']);
        $this->expectExceptionMessage('Sperrliste');
        Sammelbestellung::freigeben('1404601');
    }

    #[TestDox('Bestellen: Bestätigung per Link, Zahlungsaufforderung mit GiroCode, freier Artikel nach Prüfung, Sammelliste nur Bezahltes')]
    public function test_ablauf(): void
    {
        $artikel = Sammelbestellung::freigeben('0100003.30');
        $runde = Runde::query()->create(['name' => 'Herbst 2026', 'bestelldatum' => now()->addWeeks(3)->toDateString(), 'status' => 'offen']);
        $sammler = User::query()->create(['name' => 'Michele', 'email' => 'michele@example.org', 'status' => 'active']);
        $sammler->roleAssignments()->create(['role' => 'sammelbestellung', 'scope_type' => 'global']);

        $this->get('/bestellen')->assertOk()->assertSee('Halbarm-Klufthemd')->assertSee('Herbst 2026')->assertSee('Bestellung abschicken');
        $this->getJson('/bestellen/nachschlagen?nummer=1404601')->assertOk()->assertJson(['gesperrt' => false, 'name' => 'Komplett-Kohte']);

        $this->post('/bestellen', [
            '_t' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
            'name' => 'Erika Muster', 'email' => 'erika@example.org', 'rolle' => 'erziehungsberechtigt', 'einwilligung' => '1',
            'pos' => [
                ['mitglied' => 'Mia Muster', 'stufe' => 'woelflinge', 'menge' => 1, 'variante' => $artikel->varianten[1]->id],
                ['mitglied' => 'Mia Muster', 'stufe' => 'woelflinge', 'menge' => 2, 'frei_nummer' => '0100003.32'],
            ],
        ])->assertRedirect(route('shop.danke'));

        $bestellung = Bestellung::query()->sole();
        $this->assertSame('unbestaetigt', $bestellung->stand());
        $token = null;
        Mail::assertQueued(ShopBestellungMail::class, function (ShopBestellungMail $m) use (&$token) {
            $token = $m->token;

            return $m->art === 'doi';
        });

        // Bestätigen: Zahlung über 49 € für den Katalogartikel, freier Artikel wartet auf Prüfung.
        $this->post(route('shop.bestaetigen', $token))->assertRedirect(route('shop.show', $token));
        $bestellung->refresh();
        $this->assertSame('pruefung', $bestellung->stand());
        $zahlung = Zahlung::query()->sole();
        $this->assertSame([4900, 'offen', $runde->bestelldatum->toDateString()], [$zahlung->betrag_cent, $zahlung->status, $zahlung->faellig_am->toDateString()]);
        $this->assertStringStartsWith('RH-', $zahlung->referenz);
        Mail::assertQueued(ZahlungsaufforderungMail::class);
        Mail::assertQueued(ShopBestellungMail::class, fn (ShopBestellungMail $m) => $m->art === 'pruefen' && $m->hasTo('michele@example.org'));
        $this->get(route('shop.show', $token))->assertOk()->assertSee('wird geprüft')->assertSee($zahlung->referenz);

        // Michele bestätigt den freien Artikel: Nachforderung 2 × 49 €.
        $frei = $bestellung->positionen()->where('frei', true)->sole();
        $this->assertSame(['Halbarm-Klufthemd', 'Kragenweite 31/32', 4900], [$frei->bezeichnung, $frei->groesse, $frei->preis_cent]);
        Sammelbestellung::bestaetigen($frei, 4900);
        $this->assertSame([4900, 9800], Zahlung::query()->orderBy('id')->pluck('betrag_cent')->all());
        $this->assertSame('zahlung', $bestellung->fresh()->stand());

        // Sammelliste: erst nach Zahlungseingang.
        $this->assertCount(0, Sammelbestellung::sammelliste($runde));
        $zahlung->eingang(4900, now());
        $this->assertSame([['nummer' => '0100003.32', 'bezeichnung' => 'Halbarm-Klufthemd', 'groesse' => 'Kragenweite 31/32', 'menge' => 1]], Sammelbestellung::sammelliste($runde)->all());
        Zahlung::query()->where('betrag_cent', 9800)->sole()->eingang(9800, now());
        $this->assertSame(3, Sammelbestellung::sammelliste($runde)->sole()['menge']);
        $this->assertSame('bezahlt', $bestellung->fresh()->stand());

        // Nach Zahlung kein Storno mehr über die Website.
        $this->post(route('shop.stornieren', $token))->assertStatus(409);
    }

    #[TestDox('Gesperrte freie Artikel werden abgewiesen; ohne offene Runde kein Formular')]
    public function test_gesperrt_und_ohne_runde(): void
    {
        Sperre::query()->create(['typ' => 'kategorie', 'wert' => 'zelte']);
        $this->get('/bestellen')->assertOk()->assertSee('Gerade läuft keine Sammelbestellung')->assertDontSee('Bestellung abschicken');

        Runde::query()->create(['name' => 'Herbst', 'bestelldatum' => now()->addWeek()->toDateString(), 'status' => 'offen']);
        $this->getJson('/bestellen/nachschlagen?nummer=1404601')->assertJson(['gesperrt' => true]);
        $this->post('/bestellen', [
            '_t' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
            'name' => 'Erika', 'email' => 'erika@example.org', 'rolle' => 'mitglied', 'einwilligung' => '1',
            'pos' => [['mitglied' => 'Erika', 'menge' => 1, 'frei_nummer' => '1404601']],
        ])->assertSessionHasErrors('pos.0.frei_nummer');
        $this->assertSame(0, Bestellung::query()->count());
    }

    #[TestDox('Testbetrieb: Bestellseite nur mit Backend-Zugang, Bestellungen als Test markiert und samt Zahlungen löschbar')]
    public function test_testbetrieb(): void
    {
        Features::setzen(Features::SAMMELBESTELLUNG_FUER_ALLE, false);
        $artikel = Sammelbestellung::freigeben('0100003.30');
        Runde::query()->create(['name' => 'Probe', 'bestelldatum' => now()->addWeek()->toDateString(), 'status' => 'offen']);
        $this->get('/bestellen')->assertNotFound();

        $this->login('autor');
        $this->get('/bestellen')->assertOk()->assertSee('Testbetrieb');
        $this->post('/bestellen', [
            '_t' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
            'name' => 'Test', 'email' => 'test@example.org', 'rolle' => 'mitglied', 'einwilligung' => '1',
            'pos' => [['mitglied' => 'Test', 'menge' => 1, 'variante' => $artikel->varianten[0]->id]],
        ])->assertRedirect(route('shop.danke'));
        $bestellung = Bestellung::query()->sole();
        $this->assertTrue($bestellung->test);
        Sammelbestellung::zahlungAnfordern($bestellung);
        $this->assertStringStartsWith('TEST: ', $bestellung->fresh()->zahlungsLabel());
        $this->assertSame(1, Zahlung::query()->count());

        $this->assertSame(1, Sammelbestellung::testsLoeschen());
        $this->assertSame([0, 0], [Bestellung::query()->count(), Zahlung::query()->count()]);
    }

    #[TestDox('Schalter aus: 404; Backend nur mit Rolle Sammelbestellung (oder Admin)')]
    public function test_zugriff(): void
    {
        $this->login('kasse');
        $this->get(ArtikelResource::getUrl())->assertForbidden();

        $this->login('sammelbestellung');
        $this->get(ArtikelResource::getUrl())->assertOk();
        $this->get(RundeResource::getUrl())->assertOk();
        $this->get(BestellungResource::getUrl())->assertOk();
        Livewire::test(ListArtikel::class)->callAction('holen', ['eingabe' => '0100003.30'])->assertHasNoActionErrors();
        $this->assertSame(1, Artikel::query()->count());

        Features::setzen(Features::SAMMELBESTELLUNG, false);
        $this->get('/bestellen')->assertNotFound();
        $this->get(ArtikelResource::getUrl())->assertForbidden();
    }
}
