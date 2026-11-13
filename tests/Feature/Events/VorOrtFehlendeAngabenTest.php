<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Support\Features;
use App\Support\Teilnahmelisten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Vor Ort: Beim Unterschreiben zeigt die App, welche Angaben für die Teilnahmeliste noch fehlen,
 * und trägt sie direkt nach – auch Felder, die das Anmeldeformular gar nicht abfragt.
 */
class VorOrtFehlendeAngabenTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Features::setzen(Features::VOR_ORT, true);
        $this->event = Event::query()->create(['title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'status' => 'open', 'location' => 'Zeltplatz',
            'starts_at' => now()->startOfDay()->addHours(10), 'ends_at' => now()->startOfDay()->addDays(2)->addHours(14),
            'unterschriftenliste' => true, 'teilnahmeliste_modell' => Teilnahmelisten::RODEN, 'form_fields' => ['comment' => 'optional']]);
        $this->user = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $this->user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->user = $this->user->fresh();
    }

    private function anmeldung(string $vorname, array $data = []): EventRegistration
    {
        return $this->event->registrations()->create(['status' => 'confirmed', 'confirmed_at' => now(), 'first_name' => $vorname, 'last_name' => 'Muster',
            'email' => strtolower($vorname).'@example.org', 'data' => $data, 'consent_text_version' => 'v1', 'consent_at' => now(), 'purge_at' => now()->addYear()]);
    }

    private function daten(): array
    {
        return $this->actingAs($this->user)->getJson('/vor-ort/api/veranstaltungen/'.$this->event->getKey())->assertOk()->json();
    }

    private function angaben(EventRegistration $r, array $data): array
    {
        $id = (string) Str::uuid();

        return $this->actingAs($this->user)->postJson('/vor-ort/api/ereignisse', ['ereignisse' => [
            ['id' => $id, 'typ' => 'angaben', 'anmeldung' => $r->getKey(), 'daten' => ['data' => $data], 'zeit' => now()->toIso8601String()],
        ]])->assertOk()->json("ergebnisse.$id");
    }

    #[TestDox('Daten fürs Gerät: je Person die fehlenden Angaben des Modells, dazu die Felder, die das Formular nicht abfragt')]
    public function test_fehlt(): void
    {
        $this->anmeldung('Mia');
        $this->anmeldung('Ben', ['strasse' => 'Lindenstraße 52', 'plz' => '66740', 'wohnort' => 'Saarlouis', 'geburtsdatum' => '2010-05-17']);

        $d = $this->daten();
        $personen = collect($d['personen'])->keyBy('vorname');
        $this->assertEqualsCanonicalizing(['strasse', 'plz', 'wohnort', 'geburtsdatum'], $personen['Mia']['fehlt']);
        $this->assertSame([], $personen['Ben']['fehlt']);
        $this->assertEqualsCanonicalizing(['strasse', 'plz', 'wohnort', 'geburtsdatum'], array_column($d['liste_felder'], 'schluessel'));
        $this->assertSame('Straße und Hausnummer', collect($d['liste_felder'])->firstWhere('schluessel', 'strasse')['label']);
    }

    #[TestDox('Nachtragen: Felder ohne Abfrage im Formular werden gespeichert, ungültige PLZ und Datum abgelehnt, danach fehlt nichts mehr')]
    public function test_nachtragen(): void
    {
        $mia = $this->anmeldung('Mia');

        $this->assertStringContainsString('Postleitzahl', (string) $this->angaben($mia, ['plz' => 'abc!!'])['grund']);
        $this->assertStringContainsString('Geburtsdatum', (string) $this->angaben($mia, ['geburtsdatum' => 'gestern'])['grund']);
        $this->assertStringContainsString('Geburtsdatum', (string) $this->angaben($mia, ['geburtsdatum' => now()->addYear()->toDateString()])['grund']);

        $this->assertSame('ok', $this->angaben($mia, ['strasse' => 'Am Bahndamm 8', 'plz' => '66740', 'wohnort' => 'Saarlouis', 'geburtsdatum' => '2010-05-17'])['ergebnis']);
        $this->assertSame(['strasse' => 'Am Bahndamm 8', 'plz' => '66740', 'wohnort' => 'Saarlouis', 'geburtsdatum' => '2010-05-17'], $mia->fresh()->data);
        $this->assertSame([], collect($this->daten()['personen'])->firstWhere('vorname', 'Mia')['fehlt']);
        $this->assertSame('Unbekanntes Feld.', $this->angaben($mia, ['gibt_es_nicht' => 'x'])['grund']);
    }

    #[TestDox('LJR-Modell: Geburtsjahr fehlt nicht, wenn das Geburtsdatum bekannt ist; sonst als Jahr nachtragbar')]
    public function test_geburtsjahr(): void
    {
        $this->event->update(['teilnahmeliste_modell' => Teilnahmelisten::LJR_RLP]);
        $mia = $this->anmeldung('Mia', ['plz' => '54290', 'wohnort' => 'Trier']);
        $ben = $this->anmeldung('Ben', ['plz' => '54290', 'wohnort' => 'Trier', 'geburtsdatum' => '2010-05-17']);

        $personen = collect($this->daten()['personen'])->keyBy('vorname');
        $this->assertSame(['birth_year'], $personen['Mia']['fehlt']);
        $this->assertSame([], $personen['Ben']['fehlt']);

        $this->assertSame('ok', $this->angaben($mia, ['birth_year' => '2011'])['ergebnis']);
        $this->assertSame(2011, $mia->fresh()->birth_year);
        $this->assertSame([], collect($this->daten()['personen'])->firstWhere('vorname', 'Mia')['fehlt']);
        $this->assertNotNull($ben);
    }

    #[TestDox('Ohne Unterschriftenliste gibt es keine „fehlt“-Hinweise')]
    public function test_ohne_liste(): void
    {
        $this->event->update(['unterschriftenliste' => false]);
        $this->anmeldung('Mia');

        $d = $this->daten();
        $this->assertSame([], $d['personen'][0]['fehlt']);
        $this->assertSame([], $d['liste_felder']);
    }
}
