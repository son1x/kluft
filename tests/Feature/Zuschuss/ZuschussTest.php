<?php

namespace Tests\Feature\Zuschuss;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\Features;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Foerderrechner;
use App\Support\Zuschuesse\Kreise;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Zuschussanträge Phase 1 (docs/pflichtenheft-zuschuesse.md): Pflichtfelder
 * bei „Zuschuss beantragen“, Funktion/Anwesenheit, Zählung, Teilnahmeliste
 * und Förderschätzung.
 */
class ZuschussTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mitglieder.driver' => 'off']);
        Features::setzen(Features::ZUSCHUESSE, true);
    }

    private function lager(array $attrs = []): Event
    {
        $start = now()->addMonth()->startOfDay()->setTime(17, 0);

        return Event::query()->create([
            'title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'status' => 'open', 'location' => 'Zeltplatz Hasborn',
            'starts_at' => $start, 'ends_at' => $start->copy()->addDays(4)->setTime(14, 0),
            'form_fields' => ['birth_year' => 'required', 'diet' => 'optional'],
            'zuschuss' => true, 'zuschuss_art' => 'freizeit',
            ...$attrs,
        ]);
    }

    private function person(Event $event, string $vorname, int $alter, string $funktion = 'TN', array $data = [], bool $juleica = false): EventRegistration
    {
        return $event->registrations()->create([
            'status' => 'confirmed', 'first_name' => $vorname, 'last_name' => 'Muster', 'email' => mb_strtolower($vorname).'@example.org',
            'data' => ['strasse' => 'Hauptstraße 1', 'plz' => '66740', 'wohnort' => 'Saarlouis', 'geschlecht' => $alter % 2 ? 'w' : 'm',
                'geburtsdatum' => $event->starts_at->copy()->subYears($alter)->subMonth()->toDateString(), ...$data],
            'funktion' => $funktion, 'juleica' => $juleica, 'confirmed_at' => now(), 'consent_at' => now(), 'consent_text_version' => 'test',
        ]);
    }

    #[TestDox('Anmeldung: bei „Zuschuss beantragen“ sind Anschrift, Geburtsdatum und Geschlecht Pflicht; Geburtsjahr folgt aus dem Datum; Einwilligung nennt den Zweck')]
    public function test_anmeldeformular(): void
    {
        $event = $this->lager();
        $this->assertSame(['plz', 'wohnort', 'strasse', 'geburtsdatum', 'geschlecht', 'diet'], array_keys($event->formFields()));
        $this->assertTrue($event->formFields()['geburtsdatum']['required']);
        $this->get('/veranstaltungen/pfingstlager')->assertOk()->assertSee('type="date"', false)->assertSee('Zuschussantrag');

        $basis = ['first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'mia@example.org', 'consent' => '1', 'by_guardian' => '1', 'guardian_name' => 'Erika', 'guardian_consent' => '1'];
        $this->post('/veranstaltungen/pfingstlager/anmeldung', $basis)->assertSessionHasErrors(['plz', 'strasse', 'geburtsdatum', 'geschlecht']);
        $this->post('/veranstaltungen/pfingstlager/anmeldung', [...$basis, 'plz' => '66740', 'wohnort' => 'Saarlouis', 'strasse' => 'Hauptstr. 1',
            'geburtsdatum' => '2015-05-04', 'geschlecht' => 'w'])->assertSessionHasNoErrors();
        $mia = EventRegistration::query()->sole();
        $this->assertSame([2015, '2015-05-04', 'w', 'TN'], [$mia->birth_year, $mia->data['geburtsdatum'], $mia->data['geschlecht'], $mia->funktion]);
        $this->assertStringContainsString('zuschuss', $mia->consent_text_version);

        // Schalter aus: wie bisher, ohne Zuschussfelder.
        Features::setzen(Features::ZUSCHUESSE, false);
        $this->assertSame(['birth_year', 'diet'], array_keys($event->fresh()->formFields()));
    }

    #[TestDox('Förderschätzung: Beispiel Pfingstlager mit Land, Kreis, Stadt Saarlouis und Bistum, gedeckelt auf die ungedeckten Kosten')]
    public function test_foerderrechner(): void
    {
        $event = $this->lager(['zuschuss_finanzen' => ['verpflegung' => 150000, 'unterkunft' => 150000, 'teilnahmebeitraege' => 200000]]);
        foreach (range(1, 20) as $i) {
            $this->person($event, 'Kind'.$i, 7 + $i % 10);
        }
        foreach (range(1, 4) as $i) {
            $this->person($event, 'Leitung'.$i, 25, 'L', juleica: true);
        }
        $this->assertSame(5, count($event->days()));

        $r = Foerderrechner::rechnen($event);
        $betraege = collect($r['geber'])->pluck('betrag', 'key')->all();
        $this->assertSame(['land' => 80500, 'kreis_sls' => 28750, 'stadt_sls' => 18000, 'bistum_trier' => 61600], $betraege);
        $this->assertSame([188850, 100000, 100000], [$r['summe'], $r['ungedeckt'], $r['hoechstens']]);

        // Nur Personen aus dem Landkreis zählen beim Kreis; Stadt nur bis 17.
        $event->registrations()->where('first_name', 'like', 'Kind%')->limit(17)->get()->each(fn ($r) => $r->update(['data' => [...$r->data, 'plz' => '54290', 'wohnort' => 'Trier']]));
        $kreis = collect(Foerderrechner::rechnen($event)['geber'])->firstWhere('key', 'kreis_sls');
        $this->assertSame(0, $kreis['betrag']);
        $this->assertStringContainsString('Zu wenige', $kreis['zeilen'][0]);

        // Nicht anwesend zählt nicht.
        $event->registrations()->update(['anwesend' => false]);
        $this->assertSame(0, Foerderrechner::rechnen($event)['summe']);
    }

    #[TestDox('Kreis aus der PLZ, Zählung je Kreis und Geschlecht, fehlende Angaben, PDFs')]
    public function test_zaehlung_und_pdf(): void
    {
        $this->assertSame(['SLS', 'SB', 'HOM', 'SONST'], [Kreise::ausPlz('66740'), Kreise::ausPlz('66111'), Kreise::ausPlz('66424'), Kreise::ausPlz('54290')]);
        $event = $this->lager();
        $this->person($event, 'Mia', 9);
        $this->person($event, 'Tom', 10, data: ['plz' => '66111']);
        $this->person($event, 'Ida', 24, 'B');
        $event->registrations()->create(['status' => 'confirmed', 'first_name' => 'Ohne', 'last_name' => 'Daten', 'email' => '', 'data' => [], 'consent_at' => now(), 'consent_text_version' => 'x']);

        $z = Zuschuesse::zaehlung($event);
        $this->assertSame([1, 1, 1], [$z['kreise']['SLS']['tn'], $z['kreise']['SB']['tn'], $z['kreise']['SONST']['tn']]);
        $this->assertSame(1, $z['gesamt']['betreuung']);
        $this->assertSame([['name' => 'Ohne Daten', 'fehlt' => ['Straße', 'PLZ', 'Wohnort', 'Geburtsdatum', 'Geschlecht']]], Zuschuesse::fehlend($event));
        $this->assertSame('Ida', Zuschuesse::personen($event)->first()['vorname'], 'Betreuung zuerst');

        $this->assertStringStartsWith('%PDF', app(ZuschussPdf::class)->teilnahmeliste($event));
        $this->assertStringStartsWith('%PDF', app(ZuschussPdf::class)->schaetzung($event));
    }

    #[TestDox('Backend: Funktion beim Eintragen, Zuschuss-Angaben je Person, Sammelaktion, Übersicht und PDF-Download')]
    public function test_backend(): void
    {
        $event = $this->lager();
        $mia = $this->person($event, 'Mia', 9);
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());
        $tabelle = fn () => Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class]);

        $tabelle()->callTableAction('addParticipant', data: [
            'first_name' => 'Lea', 'last_name' => 'Leitung', 'status' => 'auto', 'funktion' => 'L', 'juleica' => true,
            'data' => ['plz' => '66740', 'wohnort' => 'Saarlouis', 'strasse' => 'Weg 2', 'geburtsdatum' => '1999-01-01', 'geschlecht' => 'w'],
        ])->assertHasNoTableActionErrors();
        $lea = EventRegistration::query()->where('first_name', 'Lea')->sole();
        $this->assertSame(['L', true], [$lea->funktion, $lea->juleica]);

        $tage = array_keys($event->days());
        $tabelle()->callTableAction('zuschuss', $mia, data: ['funktion' => 'TN', 'anwesend' => true, 'anwesend_tage' => array_slice($tage, 0, 2), 'kreis' => 'SB'])
            ->assertHasNoTableActionErrors();
        $mia->refresh();
        $this->assertSame([array_slice($tage, 0, 2), 'SB'], [$mia->anwesend_tage, $mia->kreis]);
        $this->assertSame(2, count(Zuschuesse::personen($event)->firstWhere('vorname', 'Mia')['tage']));

        $tabelle()->callTableBulkAction('nichtAnwesend', [$mia])->assertHasNoTableBulkActionErrors();
        $this->assertFalse($mia->fresh()->anwesend);

        $tabelle()->mountTableAction('zuschussUebersicht')->assertSee('Förderschätzung');
        $tabelle()->callTableAction('zuschussListe')->assertFileDownloaded('teilnahmeliste-pfingstlager.pdf');
    }
}
