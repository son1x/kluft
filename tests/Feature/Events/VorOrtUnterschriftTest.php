<?php

namespace Tests\Feature\Events;

use App\Console\Commands\UnterschriftenLoeschenCommand;
use App\Filament\Resources\Events\Pages\ZuschussAntrag;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\VorOrtEreignis;
use App\Models\VorOrtUnterschrift;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\Features;
use App\Support\Unterschriften;
use App\Support\Zuschuesse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Vor Ort, Stufe 2 (docs/vor-ort.md): digitale Unterschrift je Person und
 * Veranstaltung, Festschreiben der Teilnahmeliste, Löschen nach Frist.
 */
class VorOrtUnterschriftTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Features::verfuegbar(Features::ZUSCHUESSE)) {
            $this->markTestSkipped('Zuschussanträge gibt es in dieser Instanz nicht.');
        }
        Mail::fake();
        Storage::fake('local');
        Filament::setCurrentPanel('admin');
        config(['mitglieder.driver' => 'off']);
        Features::setzen(Features::VOR_ORT, true);
        Features::setzen(Features::ZUSCHUESSE, true);
        $this->event = Event::query()->create(['title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'status' => 'open', 'location' => 'Zeltplatz',
            'starts_at' => now()->startOfDay()->addHours(10), 'ends_at' => now()->startOfDay()->addDays(2)->addHours(14),
            'zuschuss' => true, 'zuschuss_art' => 'freizeit', 'zuschuss_geber' => ['land']]);
    }

    private function anmeldung(string $vorname): EventRegistration
    {
        return $this->event->registrations()->create(['status' => 'confirmed', 'confirmed_at' => now(), 'first_name' => $vorname, 'last_name' => 'Muster',
            'email' => strtolower($vorname).'@example.org', 'data' => ['strasse' => 'Hauptstraße 1', 'plz' => '54290', 'wohnort' => 'Trier'],
            'consent_text_version' => 'v1', 'consent_at' => now(), 'purge_at' => now()->addYear()]);
    }

    private function verwaltung(): User
    {
        $u = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $u->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $u->fresh();
    }

    /** Kleine Unterschrift als PNG-Data-URI. */
    private function bild(): string
    {
        $img = imagecreatetruecolor(200, 60);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imageline($img, 10, 40, 190, 20, imagecolorallocate($img, 17, 24, 39));
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private function unterschreiben(EventRegistration $r, mixed $bild): array
    {
        $id = (string) Str::uuid();

        return $this->postJson('/vor-ort/api/ereignisse', ['ereignisse' => [
            ['id' => $id, 'typ' => 'unterschrift', 'anmeldung' => $r->getKey(), 'daten' => ['bild' => $bild], 'zeit' => now()->toIso8601String()],
        ]])->assertOk()->json("ergebnisse.$id");
    }

    #[TestDox('Unterschreiben: verschlüsselt mit Prüfsumme gespeichert, im Protokoll nur die Prüfsumme; in den Vor-Ort-Daten sichtbar')]
    public function test_unterschreiben(): void
    {
        $mia = $this->anmeldung('Mia');
        $this->actingAs($this->verwaltung());

        $this->assertSame(['ergebnis' => 'ok'], $this->unterschreiben($mia, $this->bild()));
        $u = VorOrtUnterschrift::query()->sole();
        $this->assertSame($this->bild(), $u->bild);
        $this->assertSame(64, strlen($u->pruefsumme));
        $this->assertStringNotContainsString('base64', (string) $u->getRawOriginal('bild'));
        $this->assertSame(['pruefsumme' => $u->pruefsumme], VorOrtEreignis::query()->sole()->daten);
        $this->assertTrue(AuditLog::query()->where('action', 'vor_ort.unterschrift:'.$mia->getKey())->exists());

        $d = $this->getJson('/vor-ort/api/veranstaltungen/'.$this->event->getKey())->json();
        $this->assertStringContainsString('Ich bestätige meine Teilnahme an „Pfingstlager“', $d['unterschrift']['text']);
        $this->assertNotNull($d['personen'][0]['unterschrieben']);

        // Neu unterschreiben ersetzt die alte.
        $this->unterschreiben($mia, $this->bild());
        $this->assertSame(1, VorOrtUnterschrift::query()->count());
    }

    #[TestDox('Abgelehnt: kein Bild, kein PNG, Veranstaltung ohne Zuschussantrag')]
    public function test_abgelehnt(): void
    {
        $mia = $this->anmeldung('Mia');
        $this->actingAs($this->verwaltung());

        $this->assertSame('abgelehnt', $this->unterschreiben($mia, null)['ergebnis']);
        $this->assertSame('abgelehnt', $this->unterschreiben($mia, 'data:image/png;base64,'.base64_encode('kein bild'))['ergebnis']);
        $this->event->update(['zuschuss' => false]);
        $this->assertStringContainsString('kein Zuschussantrag', $this->unterschreiben($mia, $this->bild())['grund']);
        $this->assertSame(0, VorOrtUnterschrift::query()->count());
        $this->assertNull($this->getJson('/vor-ort/api/veranstaltungen/'.$this->event->getKey())->json('unterschrift'));
    }

    #[TestDox('Festschreiben: PDF abgelegt, Liste liefert diese Fassung, Einzelunterschriften nach 14 Tagen gelöscht')]
    public function test_festschreiben(): void
    {
        $mia = $this->anmeldung('Mia');
        $this->anmeldung('Ben');
        $verwaltung = $this->verwaltung();
        $this->actingAs($verwaltung);
        $this->unterschreiben($mia, $this->bild());
        $this->assertSame([$mia->getKey() => $this->bild()], Unterschriften::fuerListe($this->event));

        Livewire::test(ZuschussAntrag::class, ['record' => $this->event->getRouteKey()])
            ->assertActionVisible('festschreiben')
            ->callAction('festschreiben')
            ->assertNotified('Teilnahmeliste festgeschrieben (1 Unterschriften).');

        $event = $this->event->fresh();
        $this->assertNotNull($event->teilnahmeliste_am);
        Storage::disk('local')->assertExists(Unterschriften::pfad($event));
        $abgelegt = Storage::disk('local')->get(Unterschriften::pfad($event));
        $this->assertStringStartsWith('%PDF', $abgelegt);
        $this->assertSame($abgelegt, app(ZuschussPdf::class)->teilnahmeliste($event));
        $this->assertTrue(AuditLog::query()->where('action', 'zuschuss.liste_festgeschrieben:1')->exists());

        $this->travel(13)->days();
        $this->artisan(UnterschriftenLoeschenCommand::class)->assertSuccessful();
        $this->assertSame(1, VorOrtUnterschrift::query()->count());
        $this->travel(2)->days();
        $this->artisan(UnterschriftenLoeschenCommand::class)->assertSuccessful();
        $this->assertSame(0, VorOrtUnterschrift::query()->count());
        // Die festgeschriebene Liste bleibt.
        $this->assertSame($abgelegt, app(ZuschussPdf::class)->teilnahmeliste($event->fresh()));

        // Veranstaltung gelöscht: abgelegte Liste auch.
        $event->delete();
        Storage::disk('local')->assertMissing(Unterschriften::pfad($event));
    }

    #[TestDox('Teilnahmeliste (PDF) enthält die Unterschrift als Bild in der Zeile der Person')]
    public function test_bild_in_liste(): void
    {
        $mia = $this->anmeldung('Mia');
        $this->actingAs($this->verwaltung());
        $this->unterschreiben($mia, $this->bild());

        $html = view('pdf.zuschuss-teilnahmeliste', [
            'fonts' => resource_path('fonts/pdf'), 'logo' => null,
            'unterschriften' => Unterschriften::fuerListe($this->event), 'event' => $this->event, 'betreuung' => collect(),
            'teilnehmende' => Zuschuesse::personen($this->event), 'zaehlung' => Zuschuesse::zaehlung($this->event),
        ])->render();
        $this->assertStringContainsString('<img class="unterschrift-bild" src="data:image/png;base64,', $html);
    }
}
