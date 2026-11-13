<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Teilnahmelisten\ExcelVorlage;
use App\Services\TeilnehmendeListePdf;
use App\Support\Features;
use App\Support\Teilnahmelisten;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;
use ZipArchive;

/**
 * Modelle der Teilnahmeliste (Standard, Landesjugendring RLP): Spalten, Kopf, Excel nach Vorlage.
 */
class TeilnahmelistenModelleTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Filament::setCurrentPanel('admin');
        Features::setzen(Features::VOR_ORT, true);
        $this->event = Event::query()->create(['title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'status' => 'open', 'location' => 'Zeltplatz Hunsrück',
            'starts_at' => now()->startOfDay()->addHours(10), 'ends_at' => now()->startOfDay()->addDays(2)->addHours(14),
            'unterschriftenliste' => true, 'teilnahmeliste_modell' => Teilnahmelisten::LJR_RLP, 'veranstalter' => 'DPSG Stamm Beispiel']);
    }

    private function anmeldung(string $vorname, array $data = [], array $extra = []): EventRegistration
    {
        return $this->event->registrations()->create($extra + ['status' => 'confirmed', 'confirmed_at' => now(), 'first_name' => $vorname, 'last_name' => 'Muster',
            'birth_year' => 2012, 'email' => strtolower($vorname).'@example.org', 'data' => $data + ['plz' => '54290', 'wohnort' => 'Trier'],
            'consent_text_version' => 'v1', 'consent_at' => now(), 'purge_at' => now()->addYear()]);
    }

    private function verwaltung(): User
    {
        $u = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $u->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $u->fresh();
    }

    #[TestDox('Land aus der PLZ, „Teilgenommen als“, unbekanntes Modell fällt auf Standard zurück')]
    public function test_hilfen(): void
    {
        $this->assertSame(['Deutschland', 'Ausland', 'Ausland', ''], [Teilnahmelisten::land('54290'), Teilnahmelisten::land('4980'), Teilnahmelisten::land('L-1234'), Teilnahmelisten::land(null)]);
        $this->assertSame('Teilnehmer:in', Teilnahmelisten::teilgenommenAls('TN', $this->event));
        $this->assertSame('Betreuer:in für Teilnehmende ohne Behinderung', Teilnahmelisten::teilgenommenAls('L', $this->event));
        $this->event->liste_mit_behinderung = true;
        $this->assertSame('Betreuer:in für Teilnehmende mit Behinderung', Teilnahmelisten::teilgenommenAls('B', $this->event));
        $this->assertSame(Teilnahmelisten::alle()[Teilnahmelisten::STANDARD]['name'], Teilnahmelisten::modell('gibt-es-nicht')['name']);
        $this->assertSame(Teilnahmelisten::alle()[Teilnahmelisten::STANDARD]['name'], Teilnahmelisten::modell(null)['name']);
    }

    #[TestDox('LJR-Zeilen: Spalten der Vorlage, Anwesenheitstage aus „Vor Ort“, Betreuung zuerst; fehlende Pflichtangaben')]
    public function test_zeilen(): void
    {
        $tage = array_keys($this->event->days());
        $mia = $this->anmeldung('Mia', [], ['vor_ort_tage' => [$tage[0], $tage[1]]]);
        $this->anmeldung('Ben', ['plz' => '4980', 'wohnort' => 'Wiltz'], ['funktion' => 'L']);
        $this->anmeldung('Ole', ['plz' => '', 'wohnort' => '']);
        $modell = Teilnahmelisten::modell(Teilnahmelisten::LJR_RLP);

        $this->assertSame(['Name', 'Vorname', 'Wohnort', 'PLZ', 'Ort', 'Teilgenommen als', 'Anwesenheitstage', 'Geburtsjahr'], array_column($modell['spalten'], 'label'));
        $zeilen = collect(Teilnahmelisten::zeilen($this->event, $modell))->keyBy(fn (array $z) => $z['werte'][1]);
        $this->assertSame(['Muster', 'Mia', 'Deutschland', '54290', 'Trier', 'Teilnehmer:in', 2, 2012], $zeilen['Mia']['werte']);
        $this->assertSame(['Muster', 'Ben', 'Ausland', '4980', 'Wiltz', 'Betreuer:in für Teilnehmende ohne Behinderung', 3, 2012], $zeilen['Ben']['werte']);
        $this->assertSame('Ben', Teilnahmelisten::zeilen($this->event, $modell)[0]['werte'][1], 'Betreuung zuerst');

        $fehlend = Teilnahmelisten::fehlend($this->event, $modell);
        $this->assertSame([['name' => 'Ole Muster', 'fehlt' => ['PLZ', 'Wohnort']]], $fehlend);
        $this->assertSame([], Teilnahmelisten::fehlend($this->event, Teilnahmelisten::modell(null)));

        $this->assertSame([[['Veranstalter', 'DPSG Stamm Beispiel']], [['Veranstaltung', 'Pfingstlager']], [['Veranstaltungsort', 'Zeltplatz Hunsrück']], [['Datum', $this->event->dateLabel()]]], Teilnahmelisten::kopf($this->event, $modell));
        $this->event->veranstalter = null;
        $this->assertSame(config('dpsg.site.name'), Teilnahmelisten::kopf($this->event, $modell)[0][0][1]);
    }

    private function blatt(string $xlsx): array
    {
        $temp = tempnam(sys_get_temp_dir(), 'tst');
        file_put_contents($temp, $xlsx);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($temp));
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($temp);
        $blatt = simplexml_load_string($xml);
        $blatt->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return [$blatt, $xml];
    }

    #[TestDox('Excel nach Vorlage: Kopfzeile, Werte ab Zeile 2 (Zahlen als Zahlen), Formatierung und Auswahllisten bleiben; mehr als 99 Personen')]
    public function test_excel(): void
    {
        $this->anmeldung('Mia');
        $this->anmeldung('Ben', ['plz' => '4980', 'wohnort' => 'Wiltz'], ['funktion' => 'B']);
        $modell = Teilnahmelisten::modell(Teilnahmelisten::LJR_RLP);

        [$blatt, $xml] = $this->blatt(app(ExcelVorlage::class)->erzeugen($this->event, $modell));
        $zeile = fn (int $n) => collect($blatt->xpath("//m:row[@r='$n']/m:c"))->map(fn ($c) => (string) ($c->is->t ?? $c->v))->all();
        $this->assertSame(['Muster', 'Ben', 'Ausland', '4980', 'Wiltz', 'Betreuer:in für Teilnehmende ohne Behinderung', '3', '2012'], $zeile(2));
        $this->assertSame(['Muster', 'Mia', 'Deutschland', '54290', 'Trier', 'Teilnehmer:in', '3', '2012'], $zeile(3));
        $this->assertSame([], array_filter($zeile(4)), 'Rest der Vorlage bleibt leer');
        $this->assertSame('3', (string) $blatt->xpath('//m:c[@r="G3"]/m:v')[0], 'Zahl als Zahl');
        $this->assertSame('2', (string) $blatt->xpath('//m:c[@r="G3"]')[0]['s'], 'Format der Vorlage bleibt');
        $this->assertStringContainsString('Teilnehmer:in,Betreuer:in für Teilnehmende ohne Behinderung', $xml, 'Auswahlliste bleibt');
        $this->assertSame('A1:H100', (string) $blatt->dimension['ref']);

        // Über die 99 Zeilen der Vorlage hinaus.
        foreach (range(1, 100) as $i) {
            $this->anmeldung('P'.$i);
        }
        [$gross] = $this->blatt(app(ExcelVorlage::class)->erzeugen($this->event, $modell));
        $this->assertSame('A1:H103', (string) $gross->dimension['ref']);
        $this->assertSame('P99', (string) $gross->xpath('//m:row[@r="103"]/m:c[2]/m:is/m:t')[0]);
        $this->assertCount(8, $gross->xpath('//m:row[@r="103"]/m:c'));
    }

    #[TestDox('Backend: Excel-Aktion nur mit Excel-Modell; Download mit Dateiname; Hinweis auf fehlende Angaben')]
    public function test_aktion(): void
    {
        $this->anmeldung('Mia', ['plz' => '']);
        $this->actingAs($this->verwaltung());
        $manager = fn () => Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event->fresh(), 'pageClass' => EditEvent::class]);

        $manager()->assertTableActionVisible('teilnahmelisteExcel')
            ->callTableAction('teilnahmelisteExcel')
            ->assertFileDownloaded(Teilnahmelisten::dateiname($this->event, 'xlsx'));
        $manager()->callTableAction('teilnahmeliste')->assertFileDownloaded(Teilnahmelisten::dateiname($this->event, 'pdf'));

        $this->event->update(['teilnahmeliste_modell' => Teilnahmelisten::STANDARD]);
        $manager()->assertTableActionHidden('teilnahmelisteExcel');
    }

    #[TestDox('Modell Roden: Kopf mit Beginn/Ende und Aktion/Ort, Spalten des Vordrucks, Betreuung markiert, Querformat, Pflichtangaben')]
    public function test_roden(): void
    {
        $this->event->update(['teilnahmeliste_modell' => Teilnahmelisten::RODEN, 'location' => 'Rodenackerstr., 66773 Schwalbach']);
        $event = $this->event->fresh();
        $this->anmeldung('Mia', ['strasse' => 'Am Bahndamm 8', 'plz' => '', 'wohnort' => '', 'geburtsdatum' => '2010-05-17']);
        $this->anmeldung('Ben', ['strasse' => 'Lindenstraße 52', 'plz' => '66740', 'wohnort' => 'Saarlouis', 'geburtsdatum' => '1998-01-02'], ['funktion' => 'L']);
        $modell = Teilnahmelisten::modell(Teilnahmelisten::RODEN);

        $this->assertSame('landscape', $modell['ausrichtung']);
        $this->assertSame(['Vorname', 'Nachname', 'Straße, Nr.', 'PLZ', 'Ort', 'Geburtsdatum'], array_column($modell['spalten'], 'label'));
        $kopf = Teilnahmelisten::kopf($event, $modell);
        $this->assertSame([['Beginn', $event->starts_at->format('d.m.Y')], ['Aktion', 'Pfingstlager']], $kopf[0]);
        $this->assertSame([['Ende', $event->ends_at->format('d.m.Y')], ['Ort', 'Rodenackerstr., 66773 Schwalbach']], $kopf[1]);

        $zeilen = Teilnahmelisten::zeilen($event, $modell);
        $this->assertSame(['Ben', 'Muster', 'Lindenstraße 52', '66740', 'Saarlouis', '02.01.1998'], $zeilen[0]['werte']);
        $this->assertTrue($zeilen[0]['betreuung']);
        $this->assertFalse($zeilen[1]['betreuung']);
        $this->assertSame([['name' => 'Mia Muster', 'fehlt' => ['PLZ', 'Wohnort']]], Teilnahmelisten::fehlend($event, $modell));

        $html = view('pdf.teilnahmeliste-modell', [
            'fonts' => resource_path('fonts/pdf'), 'logo' => null, 'event' => $event, 'modell' => $modell, 'kopf' => $kopf, 'zeilen' => $zeilen,
            'unterschriften' => [], 'bestaetigung' => 'Ich bestätige meine Teilnahme.',
        ])->render();
        $this->assertStringContainsString('Beginn:', $html);
        $this->assertStringContainsString('Aktion:', $html);
        $this->assertStringContainsString('<tr class="betreuung">', $html);
        $this->assertStringContainsString('= Betreuer*innen', $html);
        $this->assertStringStartsWith('%PDF', app(TeilnehmendeListePdf::class)->teilnahmeliste($event, true));
    }
}
