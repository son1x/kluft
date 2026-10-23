<?php

namespace Tests\Feature\Zahlungen;

use App\Mail\EventRegistrationMail;
use App\Mail\ParticipantCodeMail;
use App\Mail\ZahlungsaufforderungMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Zahlung;
use App\Services\EventRegistrations;
use App\Services\ParticipantExport;
use App\Support\Features;
use App\Support\Zahlungen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Teilnahmebeiträge von Veranstaltungen über den Baustein „Zahlungen“.
 */
class TeilnahmebeitragTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Features::setzen(Features::ZAHLUNGEN, true);
        Zahlungen::kontoSetzen('DPSG Beispiel', 'DE89 3704 0044 0532 0130 00', null, null);
    }

    private function event(array $attrs = []): Event
    {
        return Event::query()->create([
            'title' => 'Sommerlager', 'slug' => 'sommerlager',
            'starts_at' => now()->addMonths(2)->setTime(10, 0), 'ends_at' => now()->addMonths(2)->addDays(7),
            'location' => 'Zeltplatz', 'status' => 'open', 'form_fields' => ['birth_year' => 'required'],
            'preise' => [['bezeichnung' => 'Mitglied', 'cent' => 12000], ['bezeichnung' => 'Nichtmitglied', 'cent' => 14000]],
            ...$attrs,
        ]);
    }

    private function anmelden(Event $event, array $input = []): void
    {
        $this->post("/veranstaltungen/{$event->slug}/anmeldung", [
            'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'eltern@example.org',
            'birth_year' => now()->year - 20, 'consent' => '1', 'preis' => '1', ...$input,
        ]);
    }

    private function token(string $kind): string
    {
        $token = null;
        Mail::assertQueued(EventRegistrationMail::class, function (EventRegistrationMail $mail) use ($kind, &$token) {
            if ($mail->kind === $kind) {
                $token = $mail->token;
            }

            return $mail->kind === $kind;
        });

        return (string) $token;
    }

    #[TestDox('Preisstufe ist Pflicht; nach der Bestätigung Forderung VA mit Frist und Aufforderung mit GiroCode')]
    public function test_forderung_bei_bestaetigung(): void
    {
        $event = $this->event();
        $this->get('/veranstaltungen/sommerlager')->assertSee('Nichtmitglied')->assertSee('140,00 €');

        $this->anmelden($event, ['preis' => '']);
        $this->assertSame(0, EventRegistration::query()->count());

        $this->anmelden($event);
        $registration = EventRegistration::query()->sole();
        $this->assertSame(['Nichtmitglied', 14000], [$registration->preis_bezeichnung, $registration->preis_cent]);
        $this->assertSame(0, Zahlung::query()->count());

        $this->post('/anmeldung/bestaetigen/'.$this->token('doi'))->assertOk();
        $zahlung = Zahlung::query()->sole();
        $this->assertMatchesRegularExpression('/^VA-\d{4}-\d{4}$/', $zahlung->referenz);
        $this->assertSame(14000, $zahlung->betrag_cent);
        $this->assertTrue($zahlung->zahlbar->is($registration));
        $this->assertTrue($zahlung->faellig_am->isSameDay(now()->addDays(14)));
        Mail::assertQueued(ZahlungsaufforderungMail::class, fn ($m) => $m->hasTo('eltern@example.org') && $m->aufforderung->verwendungszweck === $zahlung->referenz);
        $this->assertSame('offen', $registration->fresh()->zahlungsstand());
    }

    #[TestDox('Ohne Funktion „Zahlungen“: Beitrag steht nur an der Anmeldung, keine Forderung')]
    public function test_ohne_funktion(): void
    {
        Features::setzen(Features::ZAHLUNGEN, false);
        $event = $this->event();
        $this->anmelden($event);
        $this->post('/anmeldung/bestaetigen/'.$this->token('doi'));

        $this->assertSame(14000, EventRegistration::query()->sole()->preis_cent);
        $this->assertSame(0, Zahlung::query()->count());
        Mail::assertNotQueued(ZahlungsaufforderungMail::class);
    }

    #[TestDox('Warteliste zahlt erst beim Nachrücken; Storno storniert offene Forderung, bezahlte bleibt für die Erstattung')]
    public function test_warteliste_und_storno(): void
    {
        $event = $this->event(['capacity' => 1, 'waitlist_enabled' => true]);
        $this->anmelden($event, ['first_name' => 'Ada']);
        $this->post('/anmeldung/bestaetigen/'.$this->token('doi'));
        $ada = EventRegistration::query()->where('first_name', 'Ada')->sole();
        $cancelAda = $this->token('confirmed');

        Mail::fake();
        $this->anmelden($event, ['first_name' => 'Ben', 'preis' => '0']);
        $this->post('/anmeldung/bestaetigen/'.$this->token('doi'));
        $ben = EventRegistration::query()->where('first_name', 'Ben')->sole();
        $this->assertSame('waitlist', $ben->status);
        $this->assertNull($ben->zahlung());

        // Ada hat schon bezahlt und storniert: ihre Zahlung bleibt (Erstattung), Ben rückt nach und bekommt eine Forderung.
        $ada->zahlung()->eingang(14000, now());
        $this->post('/anmeldung/stornieren/'.$cancelAda)->assertOk();
        $this->assertSame('bezahlt', $ada->fresh()->zahlungsstand());
        $this->assertSame('Anmeldung storniert – Erstattung prüfen', $ada->fresh()->zahlungsHinweis());

        $ben->refresh();
        $this->assertSame('confirmed', $ben->status);
        $this->assertSame(12000, $ben->zahlung()?->betrag_cent);

        app(EventRegistrations::class)->cancel($ben);
        $this->assertSame('storniert', Zahlung::query()->where('zahlbar_id', $ben->id)->sole()->status);
    }

    #[TestDox('Export und „Meine Anmeldungen“ zeigen Beitrag, Stand und Verwendungszweck')]
    public function test_export_und_meine_anmeldungen(): void
    {
        $event = $this->event();
        $this->anmelden($event);
        $this->post('/anmeldung/bestaetigen/'.$this->token('doi'));
        $zahlung = Zahlung::query()->sole();

        $export = app(ParticipantExport::class);
        $this->assertContains('Verwendungszweck', $export->headings($event));
        $zeile = $export->rows($event)[0];
        $this->assertContains($zahlung->referenz, $zeile);
        $this->assertContains('offen', $zeile);

        $this->post('/meine-anmeldungen/code', ['email' => 'eltern@example.org']);
        $code = null;
        Mail::assertSent(ParticipantCodeMail::class, function (ParticipantCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->post('/meine-anmeldungen/anmelden', ['code' => $code]);
        $this->get('/meine-anmeldungen')->assertOk()
            ->assertSee('140,00 €')->assertSee($zahlung->referenz)->assertSee('DE89 3704 0044 0532 0130 00')
            ->assertSee('data:image/png;base64,', false);
    }
}
