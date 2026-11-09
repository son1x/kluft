<?php

namespace Tests\Feature\Sammelbestellung;

use App\Filament\Pages\RuesthausKatalog;
use App\Http\Controllers\Redaktion\KatalogBildController;
use App\Models\Shop\Artikel;
use App\Models\Shop\Sperre;
use App\Models\User;
use App\Services\Sammelbestellung\Ruesthaus;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\TestCase;

/**
 * Sammelbestellung → Rüsthaus-Katalog: Kategorien und Artikel aus den
 * Übersichtsseiten des Rüsthaus (gekürzte echte Seiten in tests/Fixtures),
 * Auswahl und Übernahme im Hintergrund, Vorschaubilder über Kluft.
 */
class RuesthausKatalogTest extends TestCase
{
    use RefreshDatabase;

    private const DPSG = 'https://www.ruesthaus.de/dpsg-artikel/';

    private const HALSTUCH = 'https://www.ruesthaus.de/dpsg-artikel/pfadfinderkluft/halstuecher-knoten/17/halstuch-jungpfadfinder-fairtrade';

    private string $robots;

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/ruesthaus/'.$name));
    }

    private static function produkt(string $name, string $sku, string $preis): string
    {
        return '<html><h1 class="product--title" itemprop="name"> '.$name.' </h1><meta itemprop="price" content="'.$preis.'"/>'
            .'<span itemprop="sku"> '.$sku.' </span><div class="product--description" itemprop="description"><p>Fair gehandelt.</p></div></html>';
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['dpsg.site.level' => 'Stamm', 'dpsg.sammelbestellung_pause_ms' => 0]);
        Features::setzen(Features::ZAHLUNGEN, true);
        Features::setzen(Features::SAMMELBESTELLUNG, true);
        Storage::fake('local');
        $this->robots = self::fixture('robots.txt');

        Http::fake(function (Request $request) {
            $url = $request->url();

            return match (true) {
                $url === 'https://www.ruesthaus.de/robots.txt' => Http::response($this->robots),
                $url === 'https://www.ruesthaus.de/' => Http::response(self::fixture('start.html')),
                str_starts_with($url, self::DPSG.'?') && str_contains($url, 'p=2') => Http::response(self::fixture('kategorie-2.html')),
                str_starts_with($url, self::DPSG.'?'), $url === self::DPSG => Http::response(self::fixture('kategorie-1.html')),
                str_starts_with($url, self::HALSTUCH) => Http::response(self::produkt('Halstuch Jungpfadfinder Fairtrade', '0120103', '8.50')),
                str_contains($url, '/aufnaeher-friedenslicht') => Http::response(self::produkt('Aufnäher Friedenslicht Bethlehem 2026', '4171326', '2.50')),
                str_contains($url, '/media/image/umleitung') => Http::response('', 302, ['Location' => 'https://boese.example.org/x.png']),
                str_contains($url, '/media/image/') => Http::response('PNG-Bild', 200, ['Content-Type' => 'image/png']),
                default => Http::response('', 404),
            };
        });
    }

    private function login(string $role = 'sammelbestellung'): User
    {
        $user = User::query()->create(['name' => $role, 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    #[TestDox('Kategorien, Unterkategorien und Artikel aus den Übersichtsseiten (mit Seitenblättern); robots.txt wird beachtet')]
    public function test_auslesen(): void
    {
        $rh = app(Ruesthaus::class);
        $this->assertSame('DPSG Artikel', $rh->kategorien()[self::DPSG]);
        $this->assertArrayHasKey(self::DPSG.'pfadfinderkluft/', $rh->unterkategorien(self::DPSG));

        $liste = $rh->liste(self::DPSG);
        $this->assertSame([5, 2, false], [count($liste['artikel']), $liste['seiten'], $liste['unvollstaendig']]);
        $halstuch = collect($liste['artikel'])->firstWhere('nummer', '0120103');
        $this->assertSame([self::HALSTUCH, 850, true, 'dpsg-artikel'], [$halstuch['url'], $halstuch['preis_cent'], $halstuch['ab'], $halstuch['kategorie']]);
        $this->assertStringStartsWith('https://www.ruesthaus.de/media/image/', $halstuch['bild']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/listing/'));

        $this->assertFalse(Ruesthaus::robotsErlaubt(self::fixture('robots.txt'), '/listing/getCategory'));
        $this->assertTrue(Ruesthaus::robotsErlaubt("User-agent: *\nDisallow: /dpsg\nAllow: /dpsg-artikel/", '/dpsg-artikel/x'));

        cache()->flush();
        $this->robots = "User-agent: *\nDisallow: /dpsg-artikel/";
        $this->expectException(RuntimeException::class);
        $rh->liste(self::DPSG);
    }

    #[TestDox('Katalog im Backend: nur für die Sammelbestellung; Status neu, im Shop, gesperrt; Auswahl wird mit Kategorie übernommen')]
    public function test_auswahl_und_uebernahme(): void
    {
        $this->login('autor');
        $this->get(RuesthausKatalog::getUrl())->assertForbidden();

        $this->login();
        Sperre::query()->create(['typ' => 'nummer', 'wert' => '5210026']);
        Artikel::query()->create(['nummer' => '4101203', 'name' => 'Schon da', 'url' => 'https://www.ruesthaus.de/x', 'preis_cent' => 100, 'freigegeben' => true]);
        $this->get(RuesthausKatalog::getUrl())->assertOk()->assertSee('Rüsthaus-Katalog');

        $seite = Livewire::test(RuesthausKatalog::class)
            ->fillForm(['haupt' => self::DPSG])
            ->call('laden')
            ->assertSee('Halstuch Jungpfadfinder Fairtrade')
            ->assertSee('ab 8,50 €')
            ->assertDontSee('Schon da');
        $zeilen = collect($seite->instance()->zeilen());
        $this->assertSame(3, $zeilen->count(), 'nur neue: ohne gesperrten und vorhandenen Artikel');

        $seite->fillForm(['nur_neue' => false]);
        $alle = collect($seite->instance()->zeilen())->keyBy('nummer');
        $this->assertSame(['gesperrt', 'im Shop', 'neu'], [$alle['5210026']['status'], $alle['4101203']['status'], $alle['0120103']['status']]);
        $this->assertFalse($alle['5210026']['waehlbar']);

        $seite->set('auswahl', [self::HALSTUCH, 'https://www.ruesthaus.de/dpsg-artikel/pfadfinderkluft/aufnaeher-pins/3279/aufnaeher-friedenslicht-bethlehem-2026'])
            ->fillForm(['freigeben' => false, 'shop_kategorie' => 'Halstücher'])
            ->call('uebernehmen')
            ->assertNotified('2 Artikel werden übernommen');

        $halstuch = Artikel::query()->where('nummer', '0120103')->sole();
        $this->assertSame(['Halstücher', false], [$halstuch->kategorie, $halstuch->freigegeben]);
        $this->assertTrue(Artikel::query()->where('nummer', '4171326')->exists());
        $this->assertSame([], $seite->get('auswahl'));

        // Gesperrte Artikel lassen sich nicht unterschieben.
        $seite->set('auswahl', ['https://www.ruesthaus.de/dpsg-artikel/flaggen/x'])->call('uebernehmen')->assertNotified('Bitte mindestens einen Artikel auswählen.');
    }

    #[TestDox('Vorschaubilder kommen über Kluft: nur Rüsthaus-Bilder, nur für die Sammelbestellung, danach aus dem Zwischenspeicher')]
    public function test_bilder(): void
    {
        $bild = 'https://www.ruesthaus.de/media/image/79/bb/33/0120103_200x200.jpg';
        $this->login();
        $this->get(KatalogBildController::adresse($bild))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(KatalogBildController::adresse($bild))->assertOk();
        Http::assertSentCount(1);
        $this->get(KatalogBildController::adresse('https://boese.example.org/media/image/x.jpg'))->assertNotFound();
        $this->get(route('shop.katalog.bild', ['u' => 'unsinn']))->assertNotFound();
        // Umleitungen werden nicht verfolgt (kein Abruf von anderen Hosts).
        $this->get(KatalogBildController::adresse('https://www.ruesthaus.de/media/image/umleitung.jpg'))->assertNotFound();

        $this->login('autor');
        $this->get(KatalogBildController::adresse($bild))->assertForbidden();
    }
}
