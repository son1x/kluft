<?php

namespace Tests\Feature\Content;

use App\Models\FeatureSwitch;
use App\Models\Media;
use App\Models\Page;
use App\Support\Features;
use App\Support\Titelbild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Titelbild der Startseite: Seite „start“, Titelbilder der Instanz und die
 * schaltbare Funktion „Titelbild wechselt zufällig“.
 */
class TitelbildTest extends TestCase
{
    use RefreshDatabase;

    private const INSTANZ_BILD = [
        'src' => 'instanz/images/titelbild-test.jpg',
        'alt' => 'Testlandschaft',
        'credit' => 'Erika Beispiel / Wikimedia Commons, CC BY-SA 4.0',
        'link' => 'https://commons.wikimedia.org/wiki/File:Test.jpg',
        'position' => '60% 40%',
    ];

    private function start(?Media $cover = null): Page
    {
        return Page::query()->create(['title' => 'Pfadfinden im Test', 'slug' => 'start', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay(), 'cover_media_id' => $cover?->id]);
    }

    private function cover(): Media
    {
        return Media::query()->create(['path' => 'media/zeltlager.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'alt_text' => 'Zeltlager am See', 'credit' => 'Foto: Jana Beispiel']);
    }

    private function wechsel(bool $an): void
    {
        FeatureSwitch::query()->updateOrCreate(['key' => Features::TITELBILD_WECHSEL], ['enabled' => $an]);
        Cache::forget('features.schalter');
    }

    #[TestDox('Ohne eigenes Titelbild zeigt die Startseite das der Instanz mit verlinktem Bildnachweis')]
    public function test_instanz_titelbild(): void
    {
        config(['dpsg.titelbilder' => [self::INSTANZ_BILD]]);
        $this->start();

        $this->get('/')->assertOk()
            ->assertSee('instanz/images/titelbild-test.jpg', false)
            ->assertSee('alt="Testlandschaft"', false)
            ->assertSee('object-position: 60% 40%', false)
            ->assertSee('href="https://commons.wikimedia.org/wiki/File:Test.jpg"', false)
            ->assertSee('Erika Beispiel / Wikimedia Commons, CC BY-SA 4.0');
    }

    #[TestDox('Ohne jedes Titelbild bleibt der blaue Balken mit den Stufen-Figuren')]
    public function test_ohne_titelbild(): void
    {
        config(['dpsg.titelbilder' => []]);
        $this->start();

        $this->get('/')->assertOk()->assertSee('from-dpsg-blau', false)->assertDontSee('object-cover', false);
    }

    #[TestDox('Funktion aus: das Titelbild der Seite „start“ hat Vorrang')]
    public function test_wechsel_aus(): void
    {
        config(['dpsg.titelbilder' => [self::INSTANZ_BILD]]);
        $this->wechsel(false);
        $start = $this->start($this->cover())->load('coverMedia');

        for ($i = 0; $i < 10; $i++) {
            $this->assertNotNull(Titelbild::fuer($start)['media']);
        }
        $this->get('/')->assertOk()->assertSee('Zeltlager am See')->assertDontSee('titelbild-test.jpg');
    }

    #[TestDox('Funktion an: jeder Aufruf zieht aus Titelbild der Seite und denen der Instanz')]
    public function test_wechsel_an(): void
    {
        config(['dpsg.titelbilder' => [self::INSTANZ_BILD]]);
        $this->wechsel(true);
        $start = $this->start($this->cover())->load('coverMedia');

        $this->assertCount(2, Titelbild::kandidaten($start));
        $gesehen = [];
        for ($i = 0; $i < 60; $i++) {
            $gesehen[Titelbild::fuer($start)['media'] ? 'seite' : 'instanz'] = true;
        }
        $this->assertSame(['instanz', 'seite'], collect($gesehen)->keys()->sort()->values()->all());
    }

    #[TestDox('Unsaubere Bildposition aus der Konfiguration wird ersetzt')]
    public function test_position_bereinigt(): void
    {
        config(['dpsg.titelbilder' => [['src' => 'instanz/images/x.jpg', 'position' => '50%; background:url(x)']]]);

        $this->assertSame('50% 50%', Titelbild::kandidaten($this->start())[0]['position']);
    }

    #[TestDox('Beide Instanzen bringen ihr Titelbild mit, die Dateien liegen im Instanz-Ordner')]
    public function test_instanz_dateien(): void
    {
        foreach (['dv-trier', 'saar-hochwald'] as $instanz) {
            $config = require base_path('instanzen/'.$instanz.'/config.php');
            $this->assertNotEmpty($config['titelbilder'], $instanz);
            foreach ($config['titelbilder'] as $bild) {
                $datei = base_path('instanzen/'.$instanz.'/public/'.substr($bild['src'], strlen('instanz/')));
                $this->assertFileExists($datei);
                $this->assertFileExists(preg_replace('/\.jpg$/', '.webp', $datei));
                $this->assertNotEmpty($bild['alt']);
                $this->assertNotEmpty($bild['credit']);
            }
        }
    }
}
