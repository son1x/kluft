<?php

namespace Tests\Feature\Events;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Support\Features;
use App\Support\RoleCatalog;
use App\Support\VorOrt;
use App\Support\Zahlungen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Vor Ort: Nachmelden von Personen, die da sind, aber nicht angemeldet waren.
 */
class VorOrtNachmeldungTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Features::setzen(Features::VOR_ORT, true);
        Features::setzen(Features::ZAHLUNGEN, true);
        Zahlungen::kontoSetzen('DPSG Stamm Beispiel', 'DE89370400440532013000', null, null);
        $stufe = (string) array_key_first(array_diff_key(Event::stufen(), [Event::LEITENDE => true]));
        $this->event = Event::query()->create(['title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'starts_at' => now()->startOfDay()->addHours(10),
            'ends_at' => now()->startOfDay()->addDays(2)->addHours(14), 'status' => 'open', 'stage' => $stufe, 'max_participants' => 1,
            'form_fields' => ['diet' => 'optional', 'comment' => 'optional'],
            'preise' => [['bezeichnung' => 'Mitglied', 'cent' => 4500], ['bezeichnung' => 'Gast', 'cent' => 6000]]]);
        $this->team = User::query()->create(['name' => 'Team', 'email' => 'team@example.org', 'status' => 'active']);
        $this->team->roleAssignments()->create(['role' => RoleCatalog::leitungLiestVeranstaltungen() ? 'veranstaltungen' : 'stufenadmin', 'scope_type' => 'stage', 'scope_key' => $stufe]);
        $this->team = $this->team->fresh();
    }

    private User $team;

    /** @param  array<string, mixed>  $daten */
    private function nachmeldung(array $daten = [], ?string $id = null): array
    {
        return ['id' => $id ?? (string) Str::uuid(), 'typ' => 'nachmeldung', 'event' => $this->event->getKey(), 'zeit' => now()->toIso8601String(),
            'daten' => $daten + ['first_name' => 'Nele', 'last_name' => 'Neu', 'preis' => 0, 'einverstanden' => true, 'data' => ['diet' => 'vegan']]];
    }

    private function senden(array ...$ereignisse)
    {
        return $this->actingAs($this->team)->postJson('/vor-ort/api/ereignisse', ['ereignisse' => $ereignisse])->assertOk();
    }

    #[TestDox('Nachmelden: sofort bestätigt (auch über die Platzzahl), ohne Mail, Beitrag gefordert, Herkunft vermerkt, protokolliert')]
    public function test_nachmelden(): void
    {
        $this->event->registrations()->create(['status' => 'confirmed', 'first_name' => 'Voll', 'last_name' => 'Belegt', 'email' => '', 'consent_text_version' => 'v1', 'consent_at' => now(), 'purge_at' => now()->addYear()]);
        $id = (string) Str::uuid();

        $antwort = $this->senden($this->nachmeldung(id: $id))->json("ergebnisse.$id");
        $this->assertSame('ok', $antwort['ergebnis']);

        $r = EventRegistration::query()->findOrFail($antwort['anmeldung']);
        $this->assertSame(['confirmed', 'Nele', 'Neu', VorOrt::NACHMELDUNG], [$r->status, $r->first_name, $r->last_name, $r->consent_text_version]);
        $this->assertSame(['diet' => 'vegan'], $r->data);
        $this->assertSame(4500, $r->zahlung()->betrag_cent);
        Mail::assertNothingSent();
        $this->assertTrue(AuditLog::query()->where('action', 'vor_ort.nachmeldung:'.$r->getKey())->exists());
        $this->assertDatabaseHas('vor_ort_ereignisse', ['uuid' => $id, 'typ' => 'nachmeldung', 'ergebnis' => 'ok', 'event_registration_id' => $r->getKey()]);

        // Gleiche Kennung nochmal: nur einmal verarbeitet.
        $this->senden($this->nachmeldung(id: $id))->assertJsonPath("ergebnisse.$id.ergebnis", 'doppelt');
        $this->assertSame(2, $this->event->registrations()->count());
    }

    #[TestDox('Nachmelden: Folge-Ereignisse mit „neu:<Kennung>“ (Gerät war offline), Reihenfolge in einer Warteschlange')]
    public function test_folgeereignisse(): void
    {
        $id = (string) Str::uuid();
        $tag = now()->toDateString();
        $anwesend = ['id' => (string) Str::uuid(), 'typ' => 'anwesend', 'anmeldung' => 'neu:'.$id, 'daten' => ['tag' => $tag, 'da' => true]];
        $zahlung = ['id' => (string) Str::uuid(), 'typ' => 'zahlung', 'anmeldung' => 'neu:'.$id, 'daten' => ['cent' => 4500, 'weg' => 'paypal']];

        $ergebnisse = $this->senden($this->nachmeldung(id: $id), $anwesend, $zahlung)->json('ergebnisse');
        $this->assertSame(['ok', 'ok', 'ok'], array_column($ergebnisse, 'ergebnis'));

        $r = EventRegistration::query()->where('first_name', 'Nele')->firstOrFail();
        $this->assertSame([$tag], $r->vor_ort_tage);
        $this->assertSame('bezahlt', $r->zahlung()->status);
    }

    #[TestDox('Nachmelden abgelehnt: Duplikat, kein Einverständnis, keine Preisstufe, Pflichtfeld, fremde Veranstaltung; Folge-Ereignis läuft dann ins Leere')]
    public function test_abgelehnt(): void
    {
        $this->senden($this->nachmeldung());
        $doppelt = (string) Str::uuid();
        $this->senden($this->nachmeldung(id: $doppelt))->assertJsonPath("ergebnisse.$doppelt.grund", 'Nele Neu ist schon angemeldet.');

        foreach ([
            [['first_name' => 'Ohne', 'einverstanden' => false], 'Einverständnis'],
            [['first_name' => 'Ohne', 'preis' => null], 'Preisstufe'],
            [['first_name' => ' ', 'last_name' => 'X'], 'Vor- und Nachname'],
            [['first_name' => 'Ohne', 'data' => ['unbekannt' => 'x']], 'Unbekanntes Feld'],
        ] as [$daten, $teil]) {
            $id = (string) Str::uuid();
            $grund = $this->senden($this->nachmeldung($daten, $id))->json("ergebnisse.$id.grund");
            $this->assertStringContainsString($teil, (string) $grund);
        }

        // Folge-Ereignis auf eine abgelehnte Nachmeldung.
        $abgelehnt = (string) Str::uuid();
        $folge = (string) Str::uuid();
        $this->senden($this->nachmeldung(['first_name' => 'Ohne', 'einverstanden' => false], $abgelehnt),
            ['id' => $folge, 'typ' => 'anwesend', 'anmeldung' => 'neu:'.$abgelehnt, 'daten' => ['tag' => now()->toDateString()]])
            ->assertJsonPath("ergebnisse.$folge.ergebnis", 'abgelehnt');

        $fremd = Event::query()->create(['title' => 'Fremd', 'slug' => 'fremd', 'starts_at' => now()->addDay(), 'status' => 'open', 'stage' => Event::LEITENDE]);
        $id = (string) Str::uuid();
        $this->senden(['id' => $id, 'typ' => 'nachmeldung', 'event' => $fremd->getKey(), 'daten' => ['first_name' => 'A', 'last_name' => 'B', 'einverstanden' => true]])
            ->assertJsonPath("ergebnisse.$id.grund", 'Keine Berechtigung für diese Veranstaltung.');
        $this->assertSame(0, $fremd->registrations()->count());
        $this->assertSame(1, $this->event->registrations()->where('first_name', 'Nele')->count());
    }

    #[TestDox('Daten fürs Gerät: Preisstufen und Pflichtfelder für das Nachmelden')]
    public function test_daten(): void
    {
        $d = $this->actingAs($this->team)->getJson('/vor-ort/api/veranstaltungen/'.$this->event->getKey())->assertOk()->json();
        $this->assertSame([['nr' => 0, 'bezeichnung' => 'Mitglied', 'cent' => 4500], ['nr' => 1, 'bezeichnung' => 'Gast', 'cent' => 6000]], $d['preise']);
        $this->assertTrue($d['nachmelden']);
        $this->assertArrayHasKey('pflicht', $d['felder'][0]);
    }
}
