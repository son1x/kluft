<?php

namespace Tests\Feature\Umfragen;

use App\Filament\Resources\Umfragen\Pages\AuswertungUmfrage;
use App\Filament\Resources\Umfragen\Pages\CreateUmfrage;
use App\Filament\Resources\Umfragen\Pages\EditUmfrage;
use App\Filament\Resources\Umfragen\Pages\ListUmfragen;
use App\Filament\Resources\Umfragen\UmfrageResource;
use App\Mail\UmfrageEinladungMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Umfrage;
use App\Models\UmfrageAntwort;
use App\Models\UmfrageGewinnspiel;
use App\Models\User;
use App\Services\Umfragen\Auswertung;
use App\Services\Umfragen\Einladungen;
use App\Services\Umfragen\Export;
use App\Support\Features;
use App\Support\Umfragen;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Umfragen (docs/umfragen.md): Vorlagen, Kopie, Teilnahme mit Bedingungen,
 * Gewinnspiel getrennt, Nachfrage an Teilnehmende, Auswertung und Export.
 */
class UmfragenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Filament::setCurrentPanel('admin');
        Features::setzen(Features::UMFRAGEN, true);
    }

    private function user(string $role, string $scopeType = 'global', ?string $scopeKey = null): User
    {
        $user = User::query()->create(['name' => $role, 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);

        return $user->fresh();
    }

    /** Offene Umfrage aus der Vorlage „Nachfrage“: Anreise → (Bus) Sammelort, T-Shirt, Name, Freitext. */
    private function umfrage(array $attrs = []): Umfrage
    {
        $umfrage = Umfragen::ausVorlage('nachfrage', null);
        $umfrage->update(['status' => 'offen', 'anonym' => true, ...$attrs]);

        return $umfrage->fresh('fragen');
    }

    private function absenden(Umfrage $umfrage, array $a, array $extra = []): TestResponse
    {
        return $this->from($umfrage->url())->post(route('umfragen.absenden', $umfrage->code), [
            'a' => $a, '_t' => Crypt::encryptString((string) now()->subSeconds(20)->timestamp), ...$extra,
        ]);
    }

    #[TestDox('Vorlagen: mitgeliefert und eigene; Kopie übernimmt Fragen, ohne Antworten, Bedingungen zeigen auf die kopierten Fragen')]
    public function test_vorlagen_und_kopie(): void
    {
        $umfrage = $this->umfrage();
        [$anreise, $ort] = [$umfrage->fragen[0], $umfrage->fragen[1]];
        $this->assertSame($anreise->schluessel, $ort->bedingung['frage']);
        UmfrageAntwort::query()->create(['umfrage_id' => $umfrage->id, 'eingegangen_am' => now(), 'daten' => []]);

        $kopie = $umfrage->kopieren(['titel' => 'Kopie']);
        $this->assertSame(['entwurf', 5, 0], [$kopie->status, $kopie->fragen()->count(), $kopie->antworten()->count()]);
        $this->assertNotSame($umfrage->code, $kopie->code);
        $neu = $kopie->fragen()->get();
        $this->assertNotSame($anreise->schluessel, $neu[0]->schluessel);
        $this->assertSame($neu[0]->schluessel, $neu[1]->bedingung['frage']);

        $vorlage = $umfrage->kopieren(['ist_vorlage' => true]);
        $this->assertSame(1, Umfrage::query()->vorlagen()->count());
        $this->get(route('umfragen.zeigen', $vorlage->code))->assertNotFound();
    }

    #[TestDox('Teilnahme: Pflichtfrage, ausgeblendete Folgefrage wird verworfen, anonym ohne Namen, danach in diesem Browser erledigt')]
    public function test_teilnahme(): void
    {
        $umfrage = $this->umfrage();
        $f = $umfrage->fragen->pluck('schluessel')->all();

        $this->get($umfrage->url())->assertOk()->assertSee('Wie reist du an?')->assertSee('data-bedingung', false)->assertSee('anonym');

        $this->absenden($umfrage, [$f[1] => 'Trier'])->assertSessionHasErrors('a.'.$f[0]);
        $this->assertSame(0, $umfrage->antworten()->count());

        // Eigene Anreise: Sammelort (Folgefrage) wird verworfen.
        $this->absenden($umfrage, [$f[0] => 'Eigene An- und Abreise', $f[1] => 'Trier', $f[2] => 'M', $f[3] => 'Wölfi'])
            ->assertRedirect(route('umfragen.danke', $umfrage->code))->assertCookie('umfrage_'.$umfrage->id);
        $antwort = UmfrageAntwort::query()->sole();
        $this->assertArrayNotHasKey($f[1], $antwort->daten);
        $this->assertSame(['M', 'Wölfi'], [$antwort->daten[$f[2]]['wert'], $antwort->daten[$f[3]]['wert']]);
        $this->assertNull($antwort->name);
        $this->assertStringNotContainsString('Wölfi', (string) DB::table('umfrage_antworten')->value('daten'));

        // Bus: Sammelort gilt.
        $this->absenden($umfrage, [$f[0] => 'Mit dem Bus des Diözesanverbands', $f[1] => 'Koblenz']);
        $this->assertSame('Koblenz', UmfrageAntwort::query()->latest('id')->first()->daten[$f[1]]['wert']);

        $this->withCookie('umfrage_'.$umfrage->id, '1')->get($umfrage->url())->assertOk()->assertSee('schon teilgenommen');
        $this->absenden($umfrage, [$f[0] => 'Eigene An- und Abreise']);
        $this->assertSame(2, $umfrage->antworten()->count());
    }

    #[TestDox('Zugangscode, Zeitraum und Funktion aus')]
    public function test_zugang(): void
    {
        $umfrage = $this->umfrage(['zugangscode' => 'Lager26']);
        $this->get($umfrage->url())->assertOk()->assertSee('Zugangscode')->assertDontSee('Wie reist du an?');
        $this->post(route('umfragen.code', $umfrage->code), ['zugangscode' => 'falsch'])->assertSessionHasErrors('zugangscode');
        $this->post(route('umfragen.code', $umfrage->code), ['zugangscode' => 'lager26'])->assertRedirect($umfrage->url());
        $this->get($umfrage->url())->assertSee('Wie reist du an?');

        $umfrage->update(['endet_am' => now()->subDay()]);
        $this->get($umfrage->url())->assertSee('beendet');
        $umfrage->update(['status' => 'entwurf', 'endet_am' => null]);
        $this->get($umfrage->url())->assertNotFound();

        $umfrage->update(['status' => 'offen']);
        Features::setzen(Features::UMFRAGEN, false);
        $this->get($umfrage->url())->assertNotFound();
    }

    #[TestDox('Gewinnspiel: nur nach dem Absenden, getrennt von den Antworten gespeichert')]
    public function test_gewinnspiel(): void
    {
        $umfrage = $this->umfrage(['gewinnspiel' => true, 'gewinnspiel_text' => 'Wir verlosen ein Halstuch.']);
        $f = $umfrage->fragen->pluck('schluessel')->all();
        $this->post(route('umfragen.gewinnspiel', $umfrage->code), ['name' => 'X', 'email' => 'x@example.org', 'einwilligung' => '1'])->assertForbidden();

        $this->absenden($umfrage, [$f[0] => 'Eigene An- und Abreise']);
        $this->get(route('umfragen.danke', $umfrage->code))->assertSee('Wir verlosen ein Halstuch.');
        $this->post(route('umfragen.gewinnspiel', $umfrage->code), ['name' => 'Erika', 'email' => 'Erika@Example.org', 'einwilligung' => '1'])
            ->assertRedirect(route('umfragen.danke', $umfrage->code));

        $eintrag = UmfrageGewinnspiel::query()->sole();
        $this->assertSame(['Erika', 'erika@example.org'], [$eintrag->name, $eintrag->email]);
        $this->assertNull(UmfrageAntwort::query()->sole()->email);
        $this->post(route('umfragen.gewinnspiel', $umfrage->code), ['name' => 'Nochmal', 'email' => 'n@example.org', 'einwilligung' => '1'])->assertForbidden();
    }

    #[TestDox('Nachfrage an Teilnehmende: Einladung per Mail, nur mit persönlichem Link, Antwort der Anmeldung zugeordnet, einmal')]
    public function test_nachfrage_an_teilnehmende(): void
    {
        $event = Event::query()->create(['title' => 'Prisma', 'slug' => 'prisma', 'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addDays(3), 'location' => 'Rhens', 'status' => 'open']);
        $mia = EventRegistration::query()->create(['event_id' => $event->id, 'status' => 'confirmed', 'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'mia@example.org', 'consent_at' => now(), 'consent_text_version' => 'test']);
        EventRegistration::query()->create(['event_id' => $event->id, 'status' => 'waitlist', 'first_name' => 'Ben', 'last_name' => 'Wart', 'email' => 'ben@example.org', 'consent_at' => now(), 'consent_text_version' => 'test']);
        $umfrage = $this->umfrage(['event_id' => $event->id, 'anonym' => false]);
        $f = $umfrage->fragen->pluck('schluessel')->all();

        $this->assertSame(1, app(Einladungen::class)->einladen($umfrage));
        $this->assertSame(0, app(Einladungen::class)->einladen($umfrage));
        $token = null;
        Mail::assertQueued(UmfrageEinladungMail::class, function (UmfrageEinladungMail $mail) use (&$token) {
            $token = $mail->token;

            return $mail->hasTo('mia@example.org');
        });

        $this->get($umfrage->url())->assertOk()->assertSee('persönlichen Link');
        $link = route('umfragen.zeigen', ['code' => $umfrage->code, 't' => $token]);
        $this->get($link)->assertOk()->assertSee('nicht anonym')->assertSee('name="t"', false);

        $this->from($link)->post(route('umfragen.absenden', $umfrage->code), ['t' => $token, 'a' => [$f[0] => 'Eigene An- und Abreise'], '_t' => Crypt::encryptString((string) now()->subSeconds(20)->timestamp)])
            ->assertRedirect(route('umfragen.danke', $umfrage->code));
        $antwort = UmfrageAntwort::query()->sole();
        $this->assertSame([$mia->id, 'Mia Muster', 'mia@example.org'], [$antwort->event_registration_id, $antwort->name, $antwort->email]);
        $this->assertNotNull($umfrage->einladungen()->sole()->teilgenommen_am);
        $this->get($link)->assertSee('schon teilgenommen');
    }

    #[TestDox('Auswertung: Verteilung, Mittelwert (ohne „nicht teilgenommen“), Freitexte, Kommentare; PDF und Excel')]
    public function test_auswertung(): void
    {
        $umfrage = Umfragen::ausVorlage('veranstaltung', null);
        $umfrage->update(['status' => 'offen']);
        $f = $umfrage->fragen()->pluck('schluessel')->all();
        foreach ([['sehr gut', 'nicht teilgenommen', 'Super!'], ['gut', 'gut', null], ['sehr gut', 'gar nicht gut', 'Mehr Pausen']] as [$ganz, $programm, $text]) {
            $this->absenden($umfrage, [$f[0] => $ganz, $f[1] => $programm, $f[5] => $text], ['kommentar' => [$f[0] => $ganz === 'gut' ? 'Essen kalt' : null]]);
            $this->flushSession();
            $this->withCookies([]);
        }
        $this->assertSame(3, $umfrage->antworten()->count());

        $a = app(Auswertung::class)->zusammenfassung($umfrage);
        $this->assertSame(3, $a['teilnahmen']);
        $this->assertSame(['sehr gut' => 2, 'gut' => 1, 'nicht so gut' => 0, 'gar nicht gut' => 0], $a['fragen'][0]['verteilung']);
        $this->assertSame(1.3, $a['fragen'][0]['mittel']);
        $this->assertSame(['Essen kalt'], $a['fragen'][0]['kommentare']);
        $this->assertSame(1, $a['fragen'][1]['verteilung']['nicht teilgenommen']);
        $this->assertSame(3.0, $a['fragen'][1]['mittel']); // gut (2) und gar nicht gut (4)
        $this->assertSame(['Super!', 'Mehr Pausen'], $a['fragen'][5]['texte']);

        $this->assertStringStartsWith('%PDF', app(Export::class)->pdf($umfrage));
        $xlsx = app(Export::class)->excel($umfrage);
        ob_start();
        $xlsx->sendContent();
        $this->assertStringStartsWith('PK', (string) ob_get_clean());
    }

    #[TestDox('Backend: ab Diözesanleitung (Stufen- und Fachbereichsleitung, Vorstand, Verwaltung), nicht für Autor*innen; Seiten öffnen')]
    public function test_backend(): void
    {
        $umfrage = $this->umfrage();
        $f = $umfrage->fragen->pluck('schluessel')->all();
        $this->absenden($umfrage, [$f[0] => 'Eigene An- und Abreise', $f[3] => 'Wölfi']);

        $this->actingAs($this->user('autor'))->get(UmfrageResource::getUrl('index'))->assertForbidden();
        $referentin = $this->user('stufenadmin', 'stage', 'woelflinge');
        $this->actingAs($referentin)->get(UmfrageResource::getUrl('index'))->assertOk()->assertSee($umfrage->titel);
        $this->actingAs($referentin)->get(UmfrageResource::getUrl('edit', ['record' => $umfrage]))->assertOk()->assertSee('Nur zeigen, wenn bei');
        $this->actingAs($referentin)->get(UmfrageResource::getUrl('auswertung', ['record' => $umfrage]))->assertOk()->assertSee('Wie reist du an?');
        $this->actingAs($referentin)->get(route('umfragen.vorschau', $umfrage))->assertOk()->assertSee('Vorschau');

        Livewire::actingAs($referentin)->test(AuswertungUmfrage::class, ['record' => $umfrage->getRouteKey()])
            ->call('zeige', 'einzeln')->assertSee('Teilnahme 1 von 1')->assertSee('Wölfi');

        Livewire::actingAs($referentin)->test(ListUmfragen::class)
            ->callAction('ausVorlage', ['vorlage' => 'mit:fahrt', 'titel' => 'Korsika 2027']);
        $this->assertTrue(Umfrage::query()->where('titel', 'Korsika 2027')->where('status', 'entwurf')->exists());

        Livewire::actingAs($referentin)->test(EditUmfrage::class, ['record' => $umfrage->getRouteKey()])
            ->fillForm(['titel' => 'Infos für Prisma'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Infos für Prisma', $umfrage->fresh()->titel);
        $this->assertSame(5, $umfrage->fragen()->count());
    }

    #[TestDox('Backend-Formular: Umfrage mit Fragen, Einstellungen und Bedingung anlegen')]
    public function test_formular_neu(): void
    {
        $admin = $this->user('verwaltung');
        $lager = 'f-lager';
        $ort = 'f-ort';
        Livewire::actingAs($admin)->test(CreateUmfrage::class)
            ->fillForm([
                'titel' => 'Leiter*innenumfrage 2026', 'status' => 'offen', 'anonym' => true, 'aufbewahrung_monate' => 12,
                'fragen' => [
                    'a' => ['schluessel' => $lager, 'typ' => 'janein', 'text' => 'Möchtest du ein Diözesanlager 2028?', 'pflicht' => true, 'kommentar' => true],
                    'b' => ['schluessel' => $ort, 'typ' => 'mehrfach', 'text' => 'Wo soll es stattfinden?', 'optionen' => ['Saarland', 'Rhens', 'Westernohe'],
                        'einstellungen' => ['max' => 2, 'sonstiges' => true], 'bedingung' => ['frage' => $lager, 'werte' => ['Ja']]],
                    'c' => ['typ' => 'skala', 'text' => 'Ich fühle mich gut unterstützt.', 'einstellungen' => ['von' => 1, 'bis' => 5, 'label_von' => 'gar nicht', 'label_bis' => 'sehr']],
                ],
            ])
            ->call('create')->assertHasNoFormErrors();

        $umfrage = Umfrage::query()->where('titel', 'Leiter*innenumfrage 2026')->sole();
        $fragen = $umfrage->fragen()->get();
        $this->assertSame(['janein', 'mehrfach', 'skala'], $fragen->pluck('typ')->all());
        $this->assertSame(['frage' => $lager, 'werte' => ['Ja']], $fragen[1]->bedingung);
        $this->assertEquals(['max' => 2, 'sonstiges' => true], $fragen[1]->einstellungen);
        $this->assertNull($fragen[0]->bedingung);
        $this->assertSame($admin->id, $umfrage->erstellt_von);

        // Höchstens zwei Orte, „Sonstiges“ mit Text.
        $this->absenden($umfrage, [$lager => 'Ja', $ort => ['Saarland', 'Rhens', 'Westernohe']])->assertSessionHasErrors('a.'.$ort);
        $this->absenden($umfrage, [$lager => 'Ja', $ort => ['Rhens', 'Sonstiges'], $fragen[2]->schluessel => 4], ['sonstiges' => [$ort => 'Kastellaun']]);
        $daten = UmfrageAntwort::query()->sole()->daten;
        $this->assertSame(['wert' => ['Rhens', 'Sonstiges'], 'sonstiges' => 'Kastellaun'], $daten[$ort]);
        $this->assertSame(4, $daten[$fragen[2]->schluessel]['wert']);
    }

    #[TestDox('Löschfristen: Antworten nach der Aufbewahrung, Gewinnspiel drei Monate nach dem Ende')]
    public function test_aufraeumen(): void
    {
        $alt = $this->umfrage(['endet_am' => now()->subMonths(13), 'aufbewahrung_monate' => 12]);
        $neu = $this->umfrage(['endet_am' => now()->subMonths(4)]);
        foreach ([$alt, $neu] as $u) {
            UmfrageAntwort::query()->create(['umfrage_id' => $u->id, 'eingegangen_am' => now(), 'daten' => []]);
            UmfrageGewinnspiel::query()->create(['umfrage_id' => $u->id, 'name' => 'X', 'email' => 'x@example.org', 'eingegangen_am' => now()]);
        }

        $this->artisan('umfragen:aufraeumen')->assertSuccessful();
        $this->assertSame([0, 1], [$alt->antworten()->count(), $neu->antworten()->count()]);
        $this->assertSame(0, UmfrageGewinnspiel::query()->count());
    }
}
