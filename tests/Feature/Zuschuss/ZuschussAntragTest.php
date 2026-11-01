<?php

namespace Tests\Feature\Zuschuss;

use App\Filament\Pages\ZuschuesseUebersichtPage;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\ZuschussAntrag;
use App\Mail\ZuschussFristMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\Features;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Antrag;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Zuschussanträge Phase 4 (docs/pflichtenheft-zuschuesse.md): gemeinsame
 * Maske, Antragsteller mit Standard, Fristen und Bewilligungen, Übernahme in
 * die Anträge, Fristen-Erinnerung und Übersicht.
 */
class ZuschussAntragTest extends TestCase
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
        $start = Carbon::parse('2026-06-05 17:00');

        $event = Event::query()->create([
            'title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'status' => 'open', 'location' => 'Zeltplatz Hasborn',
            'starts_at' => $start, 'ends_at' => $start->copy()->addDays(4)->setTime(14, 0),
            'zuschuss' => true, 'zuschuss_art' => 'freizeit', 'zuschuss_geber' => ['land', 'stadt_sls', 'bistum_trier'],
            'zuschuss_finanzen' => ['verpflegung' => 150000, 'unterkunft' => 150000, 'teilnahmebeitraege' => 100000],
            ...$attrs,
        ]);
        foreach (range(1, 10) as $i) {
            $this->person($event, 'Kind'.$i, 9 + $i % 5);
        }
        $this->person($event, 'Anna', 25, 'L');
        $this->person($event, 'Ben', 22, 'B');

        return $event;
    }

    private function person(Event $event, string $vorname, int $alter, string $funktion = 'TN'): EventRegistration
    {
        return $event->registrations()->create([
            'status' => 'confirmed', 'first_name' => $vorname, 'last_name' => 'Muster', 'email' => mb_strtolower($vorname).'@example.org',
            'data' => ['strasse' => 'Hauptstraße 1', 'plz' => '66740', 'wohnort' => 'Saarlouis', 'geschlecht' => $alter % 2 ? 'w' : 'm',
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

    /** HTML statt PDF, um den Inhalt zu prüfen. */
    private function html(Event $event, string $art): string
    {
        $pdf = new class extends ZuschussPdf
        {
            public function render(string $view, array $daten, string $ausrichtung, string $titel): string
            {
                return view($view, $daten + ['fonts' => '', 'logo' => null])->render();
            }
        };

        return $pdf->unterlage($event->fresh(), $art);
    }

    #[TestDox('Fristen nach Richtlinie: Land und Kreis 3 Monate, Stadt 2 Monate (spätestens 30.09.), Bistum 8 Wochen; eigene Frist geht vor')]
    public function test_fristen(): void
    {
        $event = $this->lager();
        $this->assertSame(['2026-09-09', '2026-08-09', '2026-08-04'], [
            Antrag::regelfrist($event, 'land')->toDateString(),
            Antrag::regelfrist($event, 'stadt_sls')->toDateString(),
            Antrag::regelfrist($event, 'bistum_trier')->toDateString(),
        ]);
        $event->update(['ends_at' => Carbon::parse('2026-08-20 14:00')]);
        $this->assertSame('2026-09-30', Antrag::regelfrist($event, 'stadt_sls')->toDateString(), 'Ende des Zuschusszeitraums');

        $event->update(['zuschuss_stand' => ['land' => ['frist' => '2026-10-01']]]);
        $this->assertSame('2026-10-01', Antrag::frist($event, 'land')->toDateString());
        $this->assertSame('offen', Antrag::status($event, 'land'));
        $event->update(['zuschuss_stand' => ['land' => ['eingereicht_am' => '2026-07-01', 'bewilligt_cent' => 50000]]]);
        $this->assertSame('bewilligt', Antrag::status($event, 'land'));
    }

    #[TestDox('Maske: alle Angaben an einer Stelle, Antragsteller als Standard merken, Vermerke bleiben, Unterlagen übernehmen die Angaben')]
    public function test_maske(): void
    {
        $event = $this->lager();
        $event->update(['zuschuss_stand' => ['land' => ['erinnert' => [14]]]]);
        $this->actingAs($this->verwaltung());

        $this->get(EventResource::getUrl('zuschuss', ['record' => $event]))->assertOk()
            ->assertSee('Fristen und Bewilligungen')->assertSee('Was den Unterlagen noch fehlt');

        Livewire::test(ZuschussAntrag::class, ['record' => $event->getRouteKey()])
            ->assertFormSet(['zuschuss_antrag.name' => config('dpsg.contact.name') ?? config('dpsg.site.name')])
            ->fillForm([
                'zuschuss_antrag.anschrift' => 'Am Markt 1, 66740 Saarlouis',
                'zuschuss_antrag.bank' => 'Sparkasse Saarlouis',
                'zuschuss_antrag.iban' => 'DE02 1203 0000 0000 2020 51',
                'zuschuss_antrag.inhaber' => 'Stamm Roden',
                'zuschuss_antrag.rueckfragen' => 'Eva Kasse',
                'zuschuss_antrag.vertretung' => 'Vera Vorsitz',
                'zuschuss_antrag.leitung_name' => 'Lea Leitung',
                'zuschuss_antrag.leitung_anschrift' => 'Lagerweg 2, 66740 Saarlouis',
                'als_standard' => true,
                'zuschuss_finanzen.fahrt' => '120,50',
                'zuschuss_bericht.methodik' => 'Leben im Zeltlager',
                'zuschuss_bericht.entfernung_km' => '35',
                'zuschuss_stand.land.eingereicht_am' => '2026-07-01',
                'zuschuss_stand.land.bewilligt_am' => '2026-08-01',
                'zuschuss_stand.land.aktenzeichen' => 'LJA-123',
                'zuschuss_stand.land.bewilligt_cent' => '600',
            ])
            ->call('save')->assertHasNoFormErrors();

        $event->refresh();
        $this->assertSame(12050, $event->zuschuss_finanzen['fahrt']);
        $this->assertSame(60000, $event->zuschuss_stand['land']['bewilligt_cent']);
        $this->assertSame([14], $event->zuschuss_stand['land']['erinnert'], 'Vermerk bleibt');
        $this->assertSame('Sparkasse Saarlouis', Zuschuesse::antragsteller($event)['bank']);
        $this->assertStringNotContainsString('Vera', (string) DB::table('events')->value('zuschuss_antrag'), 'verschlüsselt');

        // Standard für neue Anträge, ohne die Leitung.
        $neu = Event::query()->create(['title' => 'Herbstfahrt', 'slug' => 'herbstfahrt', 'status' => 'open', 'starts_at' => now()->addMonth(), 'zuschuss' => true, 'zuschuss_art' => 'freizeit']);
        $werte = Antrag::werte($neu);
        $this->assertSame(['Sparkasse Saarlouis', 'Vera Vorsitz', null], [$werte['bank'], $werte['vertretung'], $werte['leitung_anschrift']]);

        $stadt = $this->html($event, 'stadt');
        $this->assertStringContainsString('Landesjugendamt Saarland', $stadt, 'bewilligte Zuschüsse Dritter');
        $this->assertStringContainsString('01.08.2026 LJA-123', $stadt);
        $this->assertStringContainsString('600,00 €', $stadt);
        $this->assertStringContainsString('Vera Vorsitz', $stadt);
        $this->assertStringContainsString('Lea Leitung, Lagerweg 2, 66740 Saarlouis', $stadt);
        $this->assertStringContainsString('Sparkasse Saarlouis', $this->html($event, 'a'));
        $this->assertStringContainsString('Eva Kasse', $this->html($event, 'bistum'));

        $fehlend = Antrag::fehlend($event);
        $this->assertSame([], $fehlend['stadt']);
        $this->assertContains('Programm', $fehlend['bistum']);
    }

    #[TestDox('Bistum: bei den anderen Zuschüssen zählt bewilligt vor beantragt vor geschätzt, abgelehnt zählt nicht')]
    public function test_bistum_andere_zuschuesse(): void
    {
        $event = $this->lager(['zuschuss_geber' => ['land', 'stadt_sls', 'bistum_trier']]);
        $event->update(['zuschuss_stand' => ['land' => ['bewilligt_cent' => 12345], 'stadt_sls' => ['abgelehnt' => true]]]);
        $html = $this->html($event, 'bistum');
        $this->assertStringContainsString('123,45 €', $html);
        $this->assertSame(0, Antrag::betrag(collect(Antrag::uebersicht($event->fresh()))->firstWhere('key', 'stadt_sls')));
    }

    #[TestDox('Fristen-Erinnerung: 14 und 3 Tage vorher, je Stufe einmal, nicht nach dem Einreichen')]
    public function test_fristen_erinnerung(): void
    {
        $event = $this->lager(['zuschuss_geber' => ['bistum_trier'], 'contact_name' => 'Lea', 'contact_email' => 'lea@example.org']);
        // Frist Bistum: 04.08.2026.
        $this->travelTo(Carbon::parse('2026-07-20 08:25'));
        $this->artisan('zuschuss:fristen')->assertSuccessful();
        Mail::assertNothingQueued();

        $this->travelTo(Carbon::parse('2026-07-22 08:25'));
        $this->artisan('zuschuss:fristen')->assertSuccessful();
        $this->artisan('zuschuss:fristen')->assertSuccessful();
        Mail::assertQueued(ZuschussFristMail::class, fn (ZuschussFristMail $m) => $m->hasTo('lea@example.org') && $m->tage === 13 && $m->frist === '04.08.2026');
        Mail::assertQueued(ZuschussFristMail::class, 1);

        $this->travelTo(Carbon::parse('2026-08-02 08:25'));
        $this->artisan('zuschuss:fristen')->assertSuccessful();
        Mail::assertQueued(ZuschussFristMail::class, 2);

        $event->refresh()->update(['zuschuss_stand' => [...$event->zuschuss_stand, 'bistum_trier' => ['eingereicht_am' => '2026-08-02']]]);
        $this->travelTo(Carbon::parse('2026-08-03 08:25'));
        $this->artisan('zuschuss:fristen')->assertSuccessful();
        Mail::assertQueued(ZuschussFristMail::class, 2);
    }

    #[TestDox('Übersicht aller Anträge: Verwaltung und Kasse, mit Hinweis auf nahe Fristen')]
    public function test_uebersicht(): void
    {
        $this->lager(['starts_at' => now()->subDays(10), 'ends_at' => now()->subDays(6), 'zuschuss_geber' => ['bistum_trier']]);
        $this->actingAs($this->verwaltung());
        $this->assertTrue(ZuschuesseUebersichtPage::canAccess());
        Livewire::test(ZuschuesseUebersichtPage::class)->assertOk()->assertSee('Pfingstlager')->assertSee('Bistum Trier (KJP)')->assertSee('noch nicht eingereicht');

        $leitung = User::query()->create(['name' => 'L', 'email' => 'l@example.org', 'status' => 'active']);
        $leitung->roleAssignments()->create(['role' => 'leitung', 'scope_type' => 'global']);
        $this->actingAs($leitung->fresh());
        $this->assertFalse(ZuschuesseUebersichtPage::canAccess());
    }
}
