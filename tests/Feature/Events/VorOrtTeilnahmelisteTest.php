<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\VorOrtUnterschrift;
use App\Services\TeilnehmendeListePdf;
use App\Support\Features;
use App\Support\Unterschriften;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Teilnahmeliste mit digitalen Unterschriften auch ohne Zuschussantrag
 * (Schalter „Unterschriftenliste“ der Veranstaltung, docs/vor-ort.md).
 */
class VorOrtTeilnahmelisteTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Filament::setCurrentPanel('admin');
        Features::setzen(Features::VOR_ORT, true);
        $this->event = Event::query()->create(['title' => 'Diözesanversammlung', 'slug' => 'dioezesanversammlung', 'status' => 'open', 'location' => 'Jugendhaus',
            'starts_at' => now()->startOfDay()->addHours(17), 'ends_at' => now()->startOfDay()->addDays(2)->addHours(14),
            'form_fields' => ['stamm' => 'optional']]);
    }

    private function anmeldung(string $vorname): EventRegistration
    {
        return $this->event->registrations()->create(['status' => 'confirmed', 'confirmed_at' => now(), 'first_name' => $vorname, 'last_name' => 'Muster',
            'email' => strtolower($vorname).'@example.org', 'consent_text_version' => 'v1', 'consent_at' => now(), 'purge_at' => now()->addYear()]);
    }

    private function verwaltung(): User
    {
        $u = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $u->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $u->fresh();
    }

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

    private function unterschreiben(User $user, EventRegistration $r): array
    {
        $id = (string) Str::uuid();

        return $this->actingAs($user)->postJson('/vor-ort/api/ereignisse', ['ereignisse' => [
            ['id' => $id, 'typ' => 'unterschrift', 'anmeldung' => $r->getKey(), 'daten' => ['bild' => $this->bild()], 'zeit' => now()->toIso8601String()],
        ]])->assertOk()->json("ergebnisse.$id");
    }

    #[TestDox('Ohne Zuschussantrag und ohne Schalter gibt es keine Unterschrift; mit Schalter schon')]
    public function test_schalter(): void
    {
        $mia = $this->anmeldung('Mia');
        $user = $this->verwaltung();

        $this->assertFalse(Unterschriften::moeglich($this->event));
        $daten = $this->actingAs($user)->getJson('/vor-ort/api/veranstaltungen/'.$this->event->getKey())->assertOk()->json();
        $this->assertNull($daten['unterschrift']);
        $this->assertSame('abgelehnt', $this->unterschreiben($user, $mia)['ergebnis']);

        $this->event->update(['unterschriftenliste' => true]);
        $daten = $this->actingAs($user)->getJson('/vor-ort/api/veranstaltungen/'.$this->event->getKey())->assertOk()->json();
        $this->assertStringContainsString('Ich bestätige meine Teilnahme an „Diözesanversammlung“', $daten['unterschrift']['text']);
        $this->assertSame('ok', $this->unterschreiben($user, $mia)['ergebnis']);
        $this->assertSame(1, VorOrtUnterschrift::query()->count());
    }

    #[TestDox('Teilnahmeliste (PDF) zeigt die Unterschrift in der Zeile; Festschreiben legt sie ab, Löschen nach der Frist')]
    public function test_liste_und_festschreiben(): void
    {
        $this->event->update(['unterschriftenliste' => true]);
        $mia = $this->anmeldung('Mia');
        $this->anmeldung('Ben');
        $user = $this->verwaltung();
        $this->unterschreiben($user, $mia);

        $liste = app(TeilnehmendeListePdf::class);
        $this->assertStringStartsWith('%PDF', $liste->teilnahmeliste($this->event));

        $html = view('pdf.teilnehmende-liste', [
            'fonts' => resource_path('fonts/pdf'), 'logo' => null, 'event' => $this->event, 'spalten' => [], 'tage' => [], 'mitSorge' => false,
            'personen' => collect([['id' => $mia->getKey(), 'name' => 'Muster, Mia', 'warteliste' => false, 'werte' => [], 'sorge' => null]]),
            'unterschriften' => Unterschriften::fuerListe($this->event), 'titelText' => 'Teilnahmeliste', 'bestaetigung' => Unterschriften::text($this->event),
        ])->render();
        $this->assertStringContainsString('<th>Unterschrift</th>', $html);
        $this->assertStringContainsString('<img class="unterschrift-bild" src="data:image/png;base64,', $html);
        $this->assertStringContainsString('Ich bestätige meine Teilnahme', $html);

        $anzahl = Unterschriften::festschreiben($this->event, $user);
        $this->assertSame(1, $anzahl);
        $event = $this->event->fresh();
        $this->assertStringStartsWith('teilnahmelisten/', (string) $event->teilnahmeliste_datei);
        Storage::disk('local')->assertExists($event->teilnahmeliste_datei);
        $this->assertSame(Storage::disk('local')->get($event->teilnahmeliste_datei), $liste->teilnahmeliste($event));

        $this->travel(15)->days();
        $this->assertSame(1, Unterschriften::faelligeLoeschen());
        $this->assertSame(0, VorOrtUnterschrift::query()->count());
        $this->assertSame(Storage::disk('local')->get($event->teilnahmeliste_datei), $liste->teilnahmeliste($event), 'die festgeschriebene PDF bleibt');
    }

    #[TestDox('Backend: Aktionen nur mit Schalter und ohne Zuschussantrag; Festschreiben über die Aktion')]
    public function test_aktionen(): void
    {
        $this->anmeldung('Mia');
        $user = $this->verwaltung();
        $this->actingAs($user);
        $manager = fn () => Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event->fresh(), 'pageClass' => EditEvent::class]);

        $manager()->assertTableActionHidden('teilnahmeliste')->assertTableActionHidden('teilnahmelisteFestschreiben');

        $this->event->update(['unterschriftenliste' => true]);
        $manager()->assertTableActionVisible('teilnahmeliste')->assertTableActionVisible('teilnahmelisteFestschreiben')
            ->callTableAction('teilnahmelisteFestschreiben')
            ->assertNotified('Teilnahmeliste festgeschrieben (0 Unterschriften).');
        $this->assertNotNull($this->event->fresh()->teilnahmeliste_am);
        $manager()->assertTableActionVisible('teilnahmelisteFestschreiben');

        // Mit Zuschussantrag gelten die Aktionen im Zuschussantrag, nicht hier.
        $this->event->update(['zuschuss' => true, 'zuschuss_art' => 'freizeit', 'zuschuss_geber' => ['land']]);
        if ($this->event->fresh()->zuschussAktiv()) {
            $manager()->assertTableActionHidden('teilnahmeliste');
        }
    }
}
