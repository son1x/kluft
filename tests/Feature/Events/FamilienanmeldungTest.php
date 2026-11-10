<?php

namespace Tests\Feature\Events;

use App\Mail\EventRegistrationMail;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Familienanmeldung: mehrere Personen in einem Formular, eine
 * Bestätigungsmail, je Person eine Anmeldung.
 */
class FamilienanmeldungTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** @param  array<string, mixed>  $attrs */
    private function event(array $attrs = []): Event
    {
        return Event::query()->create([
            'title' => 'Familientag', 'slug' => 'familientag', 'starts_at' => now()->addMonth(), 'status' => 'open',
            'mehrere_personen' => true, 'form_fields' => ['birth_year' => 'required', 'stamm' => 'optional'], ...$attrs,
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function anmelden(array $extra = []): TestResponse
    {
        return $this->post('/veranstaltungen/familientag/anmeldung', [
            'first_name' => 'Kim', 'last_name' => 'Muster', 'email' => 'Familie@Example.org', 'birth_year' => now()->year - 40,
            'stamm' => 'Stamm Sankt Georg', 'consent' => '1', ...$extra,
        ]);
    }

    private function token(): string
    {
        $token = null;
        Mail::assertQueued(EventRegistrationMail::class, function (EventRegistrationMail $m) use (&$token) {
            if ($m->kind === 'doi') {
                $token = $m->token;
            }

            return true;
        });

        return (string) $token;
    }

    #[TestDox('Drei Personen: eine Bestätigungsmail mit allen Namen; Bestätigen gibt allen einen Platz bzw. die Warteliste; Angaben übernommen')]
    public function test_familie(): void
    {
        $event = $this->event(['capacity' => 2, 'waitlist_enabled' => true]);
        $jahr = now()->year - 9;

        $this->anmelden(['by_guardian' => '1', 'guardian_name' => 'Kim Muster', 'guardian_consent' => '1', 'weitere' => [
            ['first_name' => 'Ben', 'last_name' => 'Muster', 'birth_year' => $jahr],
            ['first_name' => '', 'last_name' => '', 'birth_year' => ''],
            ['first_name' => 'Lea', 'last_name' => 'Muster', 'birth_year' => $jahr + 2],
        ]])->assertSessionHasNoErrors();

        $alle = EventRegistration::query()->orderBy('id')->get();
        $this->assertSame(['Kim', 'Ben', 'Lea'], $alle->pluck('first_name')->all());
        $this->assertCount(1, $alle->pluck('anmeldung_gruppe')->unique());
        $this->assertNotNull($alle[0]->anmeldung_gruppe);
        $this->assertSame(['Stamm Sankt Georg', 'familie@example.org', 'Kim Muster'], [$alle[1]->data['stamm'], $alle[1]->email, $alle[2]->guardian_name]);
        $this->assertNull($alle[1]->confirm_token_hash);

        Mail::assertQueued(EventRegistrationMail::class, 1);
        $doi = new EventRegistrationMail($alle[0], 'doi', 'abc');
        $this->assertStringContainsString('Ben Muster', $doi->render());
        $this->assertStringContainsString('Lea Muster', $doi->render());

        $this->post(route('registrations.confirm.submit', $this->token()))->assertOk()
            ->assertSee('Ben Muster')->assertSee('Warteliste');
        $this->assertSame(['confirmed', 'confirmed', 'waitlist'], EventRegistration::query()->orderBy('id')->pluck('status')->all());
        Mail::assertQueued(EventRegistrationMail::class, fn (EventRegistrationMail $m) => $m->kind === 'waitlist' && $m->registration->first_name === 'Lea' && filled($m->token));
    }

    #[TestDox('Prüfung: Geburtsjahr Pflicht, unter 16 nur durch Personensorgeberechtigte, höchstens 5 weitere; ohne Schalter werden weitere ignoriert')]
    public function test_pruefung(): void
    {
        $this->event();
        $this->anmelden(['weitere' => [['first_name' => 'Ben', 'last_name' => 'Muster']]])
            ->assertSessionHasErrors(['weitere.0.birth_year']);
        $this->anmelden(['weitere' => [['first_name' => 'Ben', 'last_name' => 'Muster', 'birth_year' => now()->year - 8]]])
            ->assertSessionHasErrors(['by_guardian']);
        $sechs = array_fill(0, 6, ['first_name' => 'X', 'last_name' => 'Y', 'birth_year' => now()->year - 30]);
        $this->anmelden(['weitere' => $sechs])->assertSessionHasErrors(['weitere']);
        $this->assertSame(0, EventRegistration::query()->count());

        Event::query()->update(['mehrere_personen' => false]);
        $this->anmelden(['weitere' => [['first_name' => 'Ben', 'last_name' => 'Muster', 'birth_year' => now()->year - 30]]])->assertSessionHasNoErrors();
        $this->assertSame(['Kim'], EventRegistration::query()->pluck('first_name')->all());
    }

    #[TestDox('Teilnahmebeitrag je Person; Formular zeigt den Abschnitt nur mit Schalter')]
    public function test_preis_und_formular(): void
    {
        $event = $this->event(['preise' => [['bezeichnung' => 'Erwachsene', 'cent' => 2000], ['bezeichnung' => 'Kinder', 'cent' => 1000]]]);
        if (! $event->hatTeilnahmebeitrag()) {
            $this->markTestSkipped('Teilnahmebeitrag in dieser Instanz nicht aktiv.');
        }
        $this->get('/veranstaltungen/familientag')->assertSee('Weitere Person hinzufügen');

        $this->anmelden(['preis' => '0', 'weitere' => [['first_name' => 'Ben', 'last_name' => 'Muster', 'birth_year' => now()->year - 20]]])
            ->assertSessionHasErrors(['weitere.0.preis']);
        $this->anmelden(['preis' => '0', 'weitere' => [['first_name' => 'Ben', 'last_name' => 'Muster', 'birth_year' => now()->year - 20, 'preis' => '1']]])
            ->assertSessionHasNoErrors();
        $this->assertSame([2000, 1000], EventRegistration::query()->orderBy('id')->pluck('preis_cent')->all());
    }

    #[TestDox('Abgelaufener Link löscht die ganze Gruppe; erneute Absendung legt keine Doppelten an')]
    public function test_ablauf_und_doppelt(): void
    {
        $this->event();
        $weitere = ['weitere' => [['first_name' => 'Ben', 'last_name' => 'Muster', 'birth_year' => now()->year - 30]]];
        $this->anmelden($weitere);
        $this->anmelden($weitere);
        $this->assertSame(2, EventRegistration::query()->count());

        $token = $this->token();
        $this->travel(49)->hours();
        $this->post(route('registrations.confirm.submit', $token))->assertSee('Link abgelaufen');
        $this->assertSame(0, EventRegistration::query()->count());
    }
}
