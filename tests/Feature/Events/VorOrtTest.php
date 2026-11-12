<?php

namespace Tests\Feature\Events;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\VorOrtEreignis;
use App\Support\Features;
use App\Support\RoleCatalog;
use App\Support\Zahlungen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Vor Ort (docs/vor-ort.md): Seite und Schnittstelle für Tablet und Handy.
 */
class VorOrtTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Features::setzen(Features::VOR_ORT, true);
        $this->event = Event::query()->create(['title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'starts_at' => now()->startOfDay()->addHours(10),
            'ends_at' => now()->startOfDay()->addDays(2)->addHours(14), 'status' => 'open', 'stage' => $this->stufe(),
            'form_fields' => ['tage' => 'optional', 'diet' => 'optional', 'comment' => 'optional']]);
    }

    private function stufe(): string
    {
        return (string) array_key_first(array_diff_key(Event::stufen(), [Event::LEITENDE => true]));
    }

    private function team(): User
    {
        $u = User::query()->create(['name' => 'Team', 'email' => 'team@example.org', 'status' => 'active']);
        $u->roleAssignments()->create(['role' => RoleCatalog::leitungLiestVeranstaltungen() ? 'veranstaltungen' : 'stufenadmin', 'scope_type' => 'stage', 'scope_key' => $this->stufe()]);

        return $u->fresh();
    }

    private function anmeldung(string $vorname, array $data = [], string $status = 'confirmed'): EventRegistration
    {
        return $this->event->registrations()->create(['status' => $status, 'confirmed_at' => now(), 'first_name' => $vorname, 'last_name' => 'Test',
            'email' => strtolower($vorname).'@example.org', 'data' => $data, 'consent_text_version' => 'v1', 'consent_at' => now(), 'purge_at' => now()->addYear()]);
    }

    /** @param  array<string, mixed>  $daten */
    private function ereignis(EventRegistration $r, string $typ, array $daten, ?string $id = null): array
    {
        return ['id' => $id ?? (string) Str::uuid(), 'typ' => $typ, 'anmeldung' => $r->getKey(), 'daten' => $daten, 'zeit' => now()->toIso8601String()];
    }

    private function senden(array ...$ereignisse)
    {
        return $this->postJson('/vor-ort/api/ereignisse', ['ereignisse' => $ereignisse])->assertOk();
    }

    #[TestDox('Nur angemeldet und nur mit eingeschalteter Funktion; Liste zeigt nur betreubare Veranstaltungen')]
    public function test_zugang(): void
    {
        $this->get('/vor-ort')->assertRedirect('/admin/login');

        $team = $this->team();
        $this->actingAs($team)->get('/vor-ort')->assertOk()->assertSee('Vor Ort');
        Event::query()->create(['title' => 'Fremd', 'slug' => 'fremd', 'starts_at' => now()->addDay(), 'status' => 'open', 'stage' => Event::LEITENDE]);
        Event::query()->create(['title' => 'Lange her', 'slug' => 'alt', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subWeeks(3), 'status' => 'open', 'stage' => $this->stufe()]);
        $this->getJson('/vor-ort/api/veranstaltungen')->assertOk()
            ->assertJsonPath('veranstaltungen.0.titel', 'Pfingstlager')->assertJsonCount(1, 'veranstaltungen');
        $fremd = Event::query()->where('slug', 'fremd')->sole();
        $this->getJson('/vor-ort/api/veranstaltungen/'.$fremd->getKey())->assertForbidden();

        Features::setzen(Features::VOR_ORT, false);
        $this->get('/vor-ort')->assertNotFound();
    }

    #[TestDox('Daten: bestätigte Personen mit erwarteten Tagen, Verpflegung in Summe, Zahlungsstand')]
    public function test_daten(): void
    {
        Features::setzen(Features::ZAHLUNGEN, true);
        $this->event->update(['preise' => [['bezeichnung' => 'Mitglied', 'cent' => 4500]]]);
        $tage = array_keys($this->event->fresh()->days());
        $mia = $this->anmeldung('Mia', ['tage' => [$tage[1]], 'diet' => 'vegan']);
        $this->anmeldung('Ben', ['diet' => 'vegan']);
        $this->anmeldung('Ole', [], 'waitlist');
        Zahlungen::anlegen($mia, 4500, 'VA', 'Pfingstlager');

        $d = $this->actingAs($this->team())->getJson('/vor-ort/api/veranstaltungen/'.$this->event->getKey())->assertOk()->json();
        $this->assertSame(['Ben', 'Mia'], array_column($d['personen'], 'vorname'));
        $this->assertCount(3, $d['tage']);
        $mia = collect($d['personen'])->firstWhere('vorname', 'Mia');
        $this->assertSame([$tage[1]], $mia['erwartet']);
        $this->assertSame(['stand' => 'offen', 'offen_cent' => 4500, 'betrag_cent' => 4500], $mia['zahlung']);
        $this->assertSame([['label' => 'Vegan', 'anzahl' => 2]], $d['verpflegung']);
        $this->assertSame(['bar' => 'Bar', 'karte' => 'Karte (SumUp)', 'paypal' => 'PayPal'], $d['zahlwege']);
    }

    #[TestDox('Anwesenheit je Tag; dieselbe Kennung zweimal wird nur einmal verbucht')]
    public function test_anwesend(): void
    {
        $mia = $this->anmeldung('Mia');
        $tag = now()->toDateString();
        $id = (string) Str::uuid();
        $this->actingAs($this->team());

        $this->senden($this->ereignis($mia, 'anwesend', ['tag' => $tag, 'da' => true], $id))->assertJsonPath("ergebnisse.$id.ergebnis", 'ok');
        $this->senden($this->ereignis($mia, 'anwesend', ['tag' => $tag, 'da' => true], $id))->assertJsonPath("ergebnisse.$id.ergebnis", 'doppelt');
        $this->assertSame([$tag], $mia->fresh()->vor_ort_tage);

        $this->senden($this->ereignis($mia, 'anwesend', ['tag' => $tag, 'da' => false]));
        $this->assertNull($mia->fresh()->vor_ort_tage);

        $falsch = (string) Str::uuid();
        $this->senden($this->ereignis($mia, 'anwesend', ['tag' => '2001-01-01'], $falsch))
            ->assertJsonPath("ergebnisse.$falsch.ergebnis", 'abgelehnt');
        $this->assertSame(3, VorOrtEreignis::query()->count());
    }

    #[TestDox('Kassieren vor Ort: Zahlungseingang mit Zahlungsart als Notiz, nur bei offener Forderung, protokolliert')]
    public function test_bar(): void
    {
        Features::setzen(Features::ZAHLUNGEN, true);
        $mia = $this->anmeldung('Mia');
        $ben = $this->anmeldung('Ben');
        $zahlung = Zahlungen::anlegen($mia, 4500, 'VA', 'Pfingstlager');
        $team = $this->team();
        $this->actingAs($team);

        $falscherWeg = $this->ereignis($mia, 'zahlung', ['cent' => 4500, 'weg' => 'gold']);
        $ok = $this->ereignis($mia, 'zahlung', ['cent' => 4500, 'weg' => 'karte']);
        $zuViel = $this->ereignis($mia, 'zahlung', ['cent' => 100, 'weg' => 'bar']);
        $ohne = $this->ereignis($ben, 'zahlung', ['cent' => 4500, 'weg' => 'bar']);
        $antwort = $this->senden($falscherWeg, $ok, $zuViel, $ohne);
        $antwort->assertJsonPath("ergebnisse.{$falscherWeg['id']}.grund", 'Unbekannte Zahlungsart.')
            ->assertJsonPath("ergebnisse.{$ok['id']}.ergebnis", 'ok')
            ->assertJsonPath("ergebnisse.{$zuViel['id']}.ergebnis", 'abgelehnt')
            ->assertJsonPath("ergebnisse.{$ohne['id']}.grund", 'Es gibt keine offene Forderung.');

        $zahlung->refresh();
        $this->assertSame(['bezahlt', 4500, 'Karte (SumUp) vor Ort', $team->getKey()], [$zahlung->status, $zahlung->bezahlt_cent, $zahlung->notiz, $zahlung->erfasst_von]);
        $this->assertTrue(AuditLog::query()->where('action', 'vor_ort.zahlung:'.$mia->getKey())->exists());
    }

    #[TestDox('Angaben korrigieren: Verpflegung, Tage, Name; ungültige Werte abgelehnt; sensible Werte nicht im Protokoll')]
    public function test_angaben(): void
    {
        $this->event->update(['form_fields' => ['tage' => 'optional', 'diet' => 'optional',
            'eigene' => [['schluessel' => 'eigen_allergien', 'label' => 'Allergien', 'typ' => 'text', 'sensibel' => true]]]]);
        $tage = array_keys($this->event->fresh()->days());
        $mia = $this->anmeldung('Mia', ['diet' => 'alles', 'intern' => 'bleibt']);
        $this->actingAs($this->team());

        $ok = $this->ereignis($mia, 'angaben', ['first_name' => 'Mia-Sophie', 'data' => ['diet' => 'vegan', 'tage' => [$tage[0]]]]);
        $falsch = $this->ereignis($mia, 'angaben', ['data' => ['diet' => 'nur Pizza']]);
        $this->senden($ok, $falsch)
            ->assertJsonPath("ergebnisse.{$ok['id']}.ergebnis", 'ok')
            ->assertJsonPath("ergebnisse.{$falsch['id']}.ergebnis", 'abgelehnt');

        $mia->refresh();
        $this->assertSame('Mia-Sophie', $mia->first_name);
        $this->assertSame(['diet' => 'vegan', 'intern' => 'bleibt', 'tage' => [$tage[0]]], $mia->data);

        $s = $this->ereignis($mia, 'angaben', ['data' => ['eigen_allergien' => 'Nüsse']]);
        $this->senden($s)->assertJsonPath("ergebnisse.{$s['id']}.ergebnis", 'ok');
        $this->assertSame('Nüsse', $mia->fresh()->data['eigen_allergien']);
        $this->assertSame('(sensibel, geändert)', VorOrtEreignis::query()->where('uuid', $s['id'])->sole()->daten['data']['eigen_allergien']);
    }

    #[TestDox('Ohne Recht auf die Veranstaltung oder bei nicht bestätigter Anmeldung: abgelehnt')]
    public function test_rechte(): void
    {
        $ole = $this->anmeldung('Ole', [], 'waitlist');
        $mia = $this->anmeldung('Mia');
        $fremd = User::query()->create(['name' => 'Fremd', 'email' => 'fremd@example.org', 'status' => 'active']);
        $fremd->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own']);

        $a = $this->ereignis($mia, 'anwesend', ['tag' => now()->toDateString()]);
        $this->actingAs($fremd->fresh());
        $this->senden($a)->assertJsonPath("ergebnisse.{$a['id']}.grund", 'Keine Berechtigung für diese Veranstaltung.');

        $b = $this->ereignis($ole, 'anwesend', ['tag' => now()->toDateString()]);
        $this->actingAs($this->team());
        $this->senden($b)->assertJsonPath("ergebnisse.{$b['id']}.ergebnis", 'abgelehnt');
        $this->assertNull($mia->fresh()->vor_ort_tage);
    }

    #[TestDox('Zusatzinfos fürs Team (z. B. Zimmer): eintragen, leeren, nur bekannte Felder; in den Daten je Person')]
    public function test_zusatz(): void
    {
        $this->event->update(['zusatz_felder' => ['Zimmer', 'Zelt', 'Zimmer', ' ']]);
        $this->assertSame(['zimmer' => 'Zimmer', 'zelt' => 'Zelt'], $this->event->fresh()->zusatzFelder());
        $mia = $this->anmeldung('Mia');
        $ben = $this->anmeldung('Ben');
        $this->actingAs($this->team());

        $a = $this->ereignis($mia, 'zusatz', ['feld' => 'zimmer', 'wert' => ' 12 ']);
        $b = $this->ereignis($ben, 'zusatz', ['feld' => 'zimmer', 'wert' => '12']);
        $c = $this->ereignis($ben, 'zusatz', ['feld' => 'bett', 'wert' => 'oben']);
        $this->senden($a, $b, $c)->assertJsonPath("ergebnisse.{$c['id']}.ergebnis", 'abgelehnt');
        $this->assertSame(['zimmer' => '12'], $mia->fresh()->zusatz);

        $d = $this->getJson('/vor-ort/api/veranstaltungen/'.$this->event->getKey())->json();
        $this->assertSame([['schluessel' => 'zimmer', 'label' => 'Zimmer'], ['schluessel' => 'zelt', 'label' => 'Zelt']], $d['zusatz_felder']);
        $this->assertSame(['zimmer' => '12'], collect($d['personen'])->firstWhere('vorname', 'Ben')['zusatz']);

        $this->senden($this->ereignis($mia, 'zusatz', ['feld' => 'zimmer', 'wert' => '']));
        $this->assertNull($mia->fresh()->zusatz);
    }
}
