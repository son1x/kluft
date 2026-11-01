<?php

namespace Tests\Feature\Zuschuss;

use App\Filament\Pages\ProKopfZuschussPage;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Mail\JuleicaErinnerungMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Person;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Zuschussgeber;
use App\Services\Zuschuss\ProKopfListe;
use App\Services\Zuschuss\ZuschussExcel;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\Features;
use App\Support\NextcloudAblage;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Foerderrechner;
use App\Support\Zuschuesse\Kreise;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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
        // Alle eingebauten Zuschussgeber an, unabhängig von der Instanz.
        Zuschussgeber::query()->update(['aktiv' => true]);
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
        $tabelle()->callTableAction('zuschuss_liste')->assertFileDownloaded('teilnahmeliste-pfingstlager.pdf');
    }

    #[TestDox('Phase 2: Unterlagen je Zuschussgeber (A, Bericht, C nur bei NK/MZG/HOM, Stadt, Bistum mit erwarteten anderen Zuschüssen)')]
    public function test_unterlagen(): void
    {
        $event = $this->lager(['zuschuss_geber' => ['land', 'stadt_sls'], 'zuschuss_bericht' => ['programm' => "Sa 10–12 Uhr: Geländespiel – Anna\nSo: Abbau"]]);
        foreach (range(1, 8) as $i) {
            $this->person($event, 'Kind'.$i, 9);
        }
        $this->person($event, 'Anna', 25, 'L', juleica: true);
        $this->assertSame(['liste', 'schaetzung', 'a', 'bericht', 'stadt'], array_keys(ZuschussPdf::unterlagen($event)));
        $this->assertSame([['zeit' => 'Sa 10–12 Uhr', 'thema' => 'Geländespiel', 'referent' => 'Anna'], ['zeit' => 'So', 'thema' => 'Abbau', 'referent' => '']], Zuschuesse::programm($event));

        $this->person($event, 'Paul', 10, data: ['plz' => '66538', 'wohnort' => 'Neunkirchen']);
        $event->update(['zuschuss_geber' => ['land', 'bistum_trier']]);
        $this->assertSame(['liste', 'schaetzung', 'a', 'bericht', 'c', 'bistum'], array_keys(ZuschussPdf::unterlagen($event->fresh())));
        foreach (array_keys(ZuschussPdf::UNTERLAGEN) as $art) {
            $this->assertStringStartsWith('%PDF', app(ZuschussPdf::class)->unterlage($event->fresh(), $art), $art);
        }
        $this->assertSame('formular-a-antrag-pfingstlager.pdf', ZuschussPdf::dateiname($event, 'a'));
    }

    #[TestDox('Phase 2: Leitung aus Personen übernehmen, mit Stammdaten und gültiger Juleica')]
    public function test_leitung_uebernehmen(): void
    {
        $event = $this->lager();
        $gueltig = Person::query()->create(['name' => 'Anna Maria Leitung', 'gremium' => 'leitung', 'active' => true,
            'zuschuss_daten' => ['strasse' => 'Am Markt 1', 'plz' => '66740', 'wohnort' => 'Saarlouis', 'geburtsdatum' => '1998-04-01', 'geschlecht' => 'w', 'juleica' => true, 'juleica_bis' => now()->addYear()->toDateString()]]);
        $abgelaufen = Person::query()->create(['name' => 'Jan Alt', 'gremium' => 'leitung', 'active' => true,
            'zuschuss_daten' => ['juleica' => true, 'juleica_bis' => now()->subMonth()->toDateString()]]);
        $this->assertArrayNotHasKey('zuschuss_daten', $gueltig->toArray(), 'nie in Ausgaben');

        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());
        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('zuschussLeitung', data: ['personen' => [$gueltig->id, $abgelaufen->id], 'funktion' => 'L'])
            ->assertHasNoTableActionErrors();

        $anna = EventRegistration::query()->where('last_name', 'Leitung')->sole();
        $this->assertSame(['Anna Maria', 'L', true, 'Am Markt 1', 'confirmed'], [$anna->first_name, $anna->funktion, $anna->juleica, $anna->data['strasse'], $anna->status]);
        $this->assertFalse(EventRegistration::query()->where('last_name', 'Alt')->sole()->juleica);
    }

    #[TestDox('Phase 2: Nextcloud-Ablage legt Ordner „JJJJ-MM-TT Titel“ mit Belege-Ordner an und lädt die Unterlagen hoch; Passwort verschlüsselt')]
    public function test_nextcloud(): void
    {
        $event = $this->lager(['title' => 'Pfingstlager: Wald/Wiese', 'zuschuss_geber' => ['land']]);
        $this->person($event, 'Mia', 9);
        Http::fake(fn ($request) => Http::response('', match ($request->method()) {
            'MKCOL' => 201, 'PUT' => 201, 'PROPFIND' => 207, default => 400
        }));

        $this->assertFalse(NextcloudAblage::eingerichtet());
        NextcloudAblage::speichern('https://cloud.example.org/remote.php/dav/files/stamm/Zuschuesse/', 'stamm', 'geheim-123', null);
        $this->assertStringNotContainsString('geheim-123', (string) SiteSetting::query()->whereKey(NextcloudAblage::KEY)->value('value'));
        NextcloudAblage::speichern('https://cloud.example.org/remote.php/dav/files/stamm/Zuschuesse', 'stamm', null, null);
        $this->assertSame('geheim-123', NextcloudAblage::zugang()['passwort'], 'leer behält das Passwort');
        $this->assertStringStartsWith('Verbindung in Ordnung', NextcloudAblage::testen());

        $ordner = $event->starts_at->format('Y-m-d').' Pfingstlager Wald Wiese';
        $this->assertSame($ordner, NextcloudAblage::ordner($event));
        $this->assertSame(4, NextcloudAblage::abgleichen($event));
        $basis = 'https://cloud.example.org/remote.php/dav/files/stamm/Zuschuesse/'.rawurlencode($ordner);
        Http::assertSent(fn ($r) => $r->method() === 'MKCOL' && $r->url() === $basis);
        Http::assertSent(fn ($r) => $r->method() === 'MKCOL' && $r->url() === $basis.'/Belege');
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/teilnahmeliste-pfingstlager-waldwiese.pdf') && $r->hasHeader('Authorization'));

        $this->artisan('zuschuss:nextcloud')->assertSuccessful();
    }

    #[TestDox('Phase 3: Pro-Kopf-Liste aus dem NaMi-Export: nur aktive Mitglieder unter 18 am 01.01., PDF, Datei wird nicht gespeichert')]
    public function test_pro_kopf(): void
    {
        config(['dpsg.site.level' => 'Stamm']);
        $csv = "Nachname;Vorname;Straße;PLZ;Ort;Geburtsdatum;Mitgliedstyp;Status\n"
            ."Muster;Mia;Hauptstr. 1;66740;Saarlouis;04.05.2015;Mitglied;Aktiv\n"
            ."Alt;Anna;Weg 2;66740;Saarlouis;01.01.2009;Mitglied;Aktiv\n"
            ."Jung;Jan;Weg 3;66740;Saarlouis;02.01.2009;Mitglied;Aktiv\n"
            ."Probe;Paul;Weg 4;66740;Saarlouis;01.01.2016;Schnuppermitglied;Aktiv\n"
            ."Weg;Wilma;Weg 5;66740;Saarlouis;01.01.2014;Mitglied;Inaktiv\n"
            ."Ohne;Otto;Weg 6;66740;Saarlouis;;Mitglied;Aktiv\n";
        $pfad = tempnam(sys_get_temp_dir(), 'nami');
        file_put_contents($pfad, mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));
        $ergebnis = app(ProKopfListe::class)->lesen($pfad, 'export.csv', Carbon::create(2027, 1, 1));
        unlink($pfad);
        $this->assertSame(['Jung', 'Muster'], array_column($ergebnis['personen'], 'nachname'));
        $this->assertSame('Hauptstr. 1', $ergebnis['personen'][1]['strasse']);
        $this->assertSame(['zu_alt' => 1, 'kein_mitglied' => 2, 'ohne_datum' => 1], $ergebnis['ausgelassen']);
        $this->assertStringStartsWith('%PDF', app(ZuschussPdf::class)->proKopf($ergebnis['personen'], Carbon::create(2027, 1, 1), new Event));
        $this->assertNotEmpty(app(ProKopfListe::class)->lesen(__FILE__, 'falsch.pdf', now())['fehler']);

        // Seite: Verwaltung lädt hoch und bekommt das PDF; Datei wird nicht abgelegt.
        $user = User::query()->create(['name' => 'Kasse', 'email' => 'kasse@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());
        $this->get(ProKopfZuschussPage::getUrl())->assertOk();
        Livewire::test(ProKopfZuschussPage::class)
            ->set('data.jahr', '2027')
            ->set('data.datei', [UploadedFile::fake()->createWithContent('export.csv', $csv)])
            ->call('erzeugen')
            ->assertFileDownloaded('pro-kopf-zuschuss-2027.pdf');
    }

    #[TestDox('Phase 3: Juleica-Erinnerung zwei Monate vorher, je Ablaufdatum einmal, ohne Adresse an die Verwaltung')]
    public function test_juleica_erinnerung(): void
    {
        $bald = Person::query()->create(['name' => 'Anna Leitung', 'gremium' => 'leitung', 'active' => true, 'email' => 'anna@example.org',
            'zuschuss_daten' => ['juleica' => true, 'juleica_bis' => now()->addMonth()->toDateString()]]);
        Person::query()->create(['name' => 'Spät', 'gremium' => 'leitung', 'active' => true, 'email' => 'spaet@example.org',
            'zuschuss_daten' => ['juleica' => true, 'juleica_bis' => now()->addYear()->toDateString()]]);
        Person::query()->create(['name' => 'Ohne Mail', 'gremium' => 'leitung', 'active' => true,
            'zuschuss_daten' => ['juleica' => true, 'juleica_bis' => now()->subDay()->toDateString()]]);
        $buero = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $buero->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        $this->artisan('zuschuss:juleica')->assertSuccessful();
        Mail::assertQueued(JuleicaErinnerungMail::class, 2);
        Mail::assertQueued(JuleicaErinnerungMail::class, fn ($m) => $m->hasTo('anna@example.org') && ! $m->abgelaufen);
        Mail::assertQueued(JuleicaErinnerungMail::class, fn ($m) => $m->hasTo('buero@example.org') && $m->abgelaufen && $m->anVerwaltung);

        Mail::fake();
        $this->artisan('zuschuss:juleica')->assertSuccessful();
        Mail::assertNothingQueued();
        $bald->update(['zuschuss_daten' => [...$bald->fresh()->zuschuss_daten, 'juleica_bis' => now()->addMonths(2)->subDay()->toDateString()]]);
        $this->artisan('zuschuss:juleica')->assertSuccessful();
        Mail::assertQueued(JuleicaErinnerungMail::class, 1);
    }

    #[TestDox('Phase 3: Teilnahmeliste als Excel')]
    public function test_excel(): void
    {
        $event = $this->lager();
        $this->person($event, 'Mia', 9);
        $this->person($event, 'Ida', 24, 'L', juleica: true);
        ob_start();
        app(ZuschussExcel::class)->write($event);
        $xlsx = (string) ob_get_clean();
        $this->assertStringStartsWith('PK', $xlsx);
        $this->assertSame('zuschuss-teilnehmende-pfingstlager.xlsx', ZuschussExcel::dateiname($event));
    }
}
