<?php

namespace Tests\Feature\Zuschuss;

use App\Filament\Resources\Zuschussgeber\Pages\ManageZuschussgeber;
use App\Filament\Resources\Zuschussgeber\ZuschussgeberResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\Zuschussgeber;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\Features;
use App\Support\Zuschuesse\Antrag;
use App\Support\Zuschuesse\Foerderrechner;
use App\Support\Zuschuesse\Geber;
use App\Support\Zuschuesse\Gebiete;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Zuschussgeber modular (docs/pflichtenheft-zuschuesse.md, ZU-60 ff.):
 * Vorlagen je Instanz ein- und ausschalten, eigene Zuschussgeber mit Sätzen
 * und Wohngebiet, allgemeiner Antrag, Bistum Trier auch in Rheinland-Pfalz.
 */
class ZuschussgeberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Filament::setCurrentPanel('admin');
        config(['mitglieder.driver' => 'off']);
        Features::setzen(Features::ZUSCHUESSE, true);
    }

    private function lager(array $attrs = []): Event
    {
        $start = Carbon::parse('2026-07-10 17:00');

        return Event::query()->create([
            'title' => 'Sommerlager', 'slug' => 'sommerlager', 'status' => 'open', 'location' => 'Zeltplatz',
            'starts_at' => $start, 'ends_at' => $start->copy()->addDays(4)->setTime(14, 0),
            'zuschuss' => true, 'zuschuss_art' => 'freizeit', ...$attrs,
        ]);
    }

    private function person(Event $event, string $vorname, int $alter, string $plz, string $funktion = 'TN'): EventRegistration
    {
        return $event->registrations()->create([
            'status' => 'confirmed', 'first_name' => $vorname, 'last_name' => 'Muster', 'email' => mb_strtolower($vorname).'@example.org',
            'data' => ['strasse' => 'Weg 1', 'plz' => $plz, 'wohnort' => 'Ort', 'geschlecht' => 'w',
                'geburtsdatum' => $event->starts_at->copy()->subYears($alter)->subMonth()->toDateString()],
            'funktion' => $funktion, 'confirmed_at' => now(), 'consent_at' => now(), 'consent_text_version' => 'test',
        ]);
    }

    private function verwaltung(): User
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $user->fresh();
    }

    #[TestDox('Je Instanz eingeschaltet, was in config(dpsg.zuschuss_geber) steht; nur eingeschaltete stehen zur Wahl')]
    public function test_einschalten(): void
    {
        $this->assertEqualsCanonicalizing((array) config('dpsg.zuschuss_geber'), array_keys(Geber::aktiv()));
        Zuschussgeber::query()->update(['aktiv' => false]);
        Zuschussgeber::query()->where('key', 'bistum_trier')->update(['aktiv' => true]);
        $this->assertSame(['bistum_trier' => 'Bistum Trier (KJP)'], Geber::aktiv());

        // Ohne Auswahl an der Veranstaltung: alle eingeschalteten; schon gewählte bleiben sichtbar.
        $event = $this->lager(['zuschuss_geber' => ['land']]);
        $this->assertSame(['land' => 'Landesjugendamt Saarland (ausgeschaltet)', 'bistum_trier' => 'Bistum Trier (KJP)'], Geber::auswahl($event));
        $this->assertSame(['bistum_trier'], Geber::vonEvent($this->lager(['slug' => 'b'])));
        $this->assertSame(['liste', 'schaetzung', 'bistum'], array_keys(ZuschussPdf::unterlagen($this->lager(['slug' => 'c']))));
    }

    #[TestDox('Bistum Trier: Saarland ohne Saarpfalz-Kreis und Rheinland-Pfalz (Regionen Trier und Koblenz); unsichere PLZ zählen nicht')]
    public function test_bistum_trier_rlp(): void
    {
        $this->assertSame([true, true, true, false, false, null, false, true], array_map([Gebiete::class, 'bistumTrier'],
            ['54290', '56068', '66740', '66424', '56410', '57518', '55546', '55543']));
        $this->assertFalse(Gebiete::bistumTrier('10115'), 'Berlin');

        $event = $this->lager(['zuschuss_geber' => ['bistum_trier']]);
        foreach (range(1, 8) as $i) {
            $this->person($event, 'Kind'.$i, 10, $i <= 6 ? '54290' : '57518');
        }
        $this->person($event, 'Lea', 25, '54290', 'L');
        $bistum = collect(Foerderrechner::rechnen($event)['geber'])->firstWhere('key', 'bistum_trier');
        $this->assertGreaterThan(0, $bistum['betrag'], 'Trier (RLP) zählt');
        $this->assertStringContainsString('nicht sicher', implode(' ', $bistum['hinweise']));
    }

    #[TestDox('Eigener Zuschussgeber im Backend: Satz, Alter, Wohngebiet, Frist; allgemeiner Antrag als PDF')]
    public function test_eigener_geber(): void
    {
        $this->actingAs($this->verwaltung());
        $this->assertTrue(ZuschussgeberResource::canAccess());
        Livewire::test(ManageZuschussgeber::class)
            ->callAction('create', [
                'name' => 'Kreisjugendamt Trier-Saarburg', 'aktiv' => true,
                'regeln' => ['richtlinie' => 'Jugendförderrichtlinie 2024', 'vordruck' => 'allgemein', 'anschrift' => "Willy-Brandt-Platz 1\n54290 Trier",
                    'saetze' => ['freizeit' => '2,00'], 'min_tn' => 5, 'alter_von' => 6, 'alter_bis' => 17, 'betreuer' => 'schluessel',
                    'gebiet' => ['07235'], 'gebiet_regel' => 'nur', 'frist_wochen' => 6],
            ])->assertHasNoActionErrors();
        $geber = Zuschussgeber::query()->where('name', 'Kreisjugendamt Trier-Saarburg')->sole();
        $this->assertSame('eigen_kreisjugendamt_trier_saarburg', $geber->key);
        $this->assertSame(200, $geber->regeln['saetze']['freizeit']);

        $event = $this->lager(['zuschuss_geber' => [$geber->key]]);
        foreach (range(1, 6) as $i) {
            $this->person($event, 'Kind'.$i, 10, '54329'); // Konz, Kreis Trier-Saarburg
        }
        $this->person($event, 'Stadtkind', 10, '54290'); // Stadt Trier: nicht im Kreis
        $this->person($event, 'Lea', 25, '54329', 'L');

        $r = collect(Foerderrechner::rechnen($event)['geber'])->firstWhere('key', $geber->key);
        // 6 TN + 1 Betreuerin, je 5 Tage, 2 € = 70 €.
        $this->assertSame(7000, $r['betrag']);
        $this->assertStringContainsString('6 Teilnehmende', $r['zeilen'][1]);
        $this->assertSame('2026-08-25', Antrag::frist($event, $geber->key)->toDateString());

        $art = 'antrag_'.$geber->key;
        $this->assertArrayHasKey($art, ZuschussPdf::unterlagen($event));
        $this->assertStringStartsWith('%PDF', app(ZuschussPdf::class)->unterlage($event, $art));
        $this->assertSame('antrag-kreisjugendamt-trier-saarburg-sommerlager.pdf', ZuschussPdf::dateiname($event, $art));

        // Vorlagen lassen sich nicht löschen, eigene schon.
        $this->assertFalse(ZuschussgeberResource::canDelete(Zuschussgeber::query()->where('key', 'land')->sole()));
        $this->assertTrue(ZuschussgeberResource::canDelete($geber));
    }

    #[TestDox('Eigener Geber mit Formular A: Kreis aus dem Gebiet wird als Jugendamt angekreuzt')]
    public function test_formular_a_fuer_anderen_kreis(): void
    {
        Zuschussgeber::query()->create(['key' => 'eigen_mzg', 'name' => 'Kreisjugendamt Merzig-Wadern', 'aktiv' => true,
            'regeln' => ['vordruck' => 'formular_a', 'saetze' => ['freizeit' => 250], 'gebiet' => ['10042']]]);
        $event = $this->lager(['zuschuss_geber' => ['eigen_mzg']]);
        $this->assertSame(['MZG'], Geber::jugendaemterFormularA($event));
        $this->assertArrayHasKey('a', ZuschussPdf::unterlagen($event));
        $this->assertArrayNotHasKey('antrag_eigen_mzg', ZuschussPdf::unterlagen($event));
    }
}
