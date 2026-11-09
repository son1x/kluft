<?php

namespace Tests\Feature\Events;

use App\Mail\EventRegistrationMail;
use App\Mail\TestRecipientFilter;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * „Vorab extern freigeben“: Anmeldung vor dem Livegang über das Testsystem,
 * auch als iframe auf der alten Website (dpsg.einbetten_von).
 */
class AnmeldungEinbettenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dpsg.einbetten_von' => ['https://www.dpsg-trier.de', 'https://dpsg-trier.de/', 'http://unsicher.example.org', 'javascript:alert(1)']]);
    }

    private function event(string $slug, bool $frei): Event
    {
        return Event::query()->create([
            'title' => 'Aktion '.$slug, 'slug' => $slug, 'starts_at' => now()->addMonth(), 'status' => 'open',
            'adults_only' => true, 'extern_freigegeben' => $frei, 'form_fields' => ['phone' => 'optional'],
        ]);
    }

    /** @return array<string, string> */
    private static function anmeldung(array $extra = []): array
    {
        return ['first_name' => 'Kim', 'last_name' => 'Leitung', 'email' => 'kim@example.org', 'consent' => '1', ...$extra];
    }

    #[TestDox('Einbett-Ansicht nur bei Freigabe; iframe nur von den eingetragenen https-Websites')]
    public function test_einbetten(): void
    {
        Mail::fake();
        $this->event('intern', false);
        $this->event('aktion', true);

        $this->get('/veranstaltungen/intern/einbetten')->assertNotFound();
        $this->get('/veranstaltungen/aktion')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $antwort = $this->get('/veranstaltungen/aktion/einbetten')->assertOk()
            ->assertSee('data-einbetten', false)
            ->assertSee('name="einbetten" value="1"', false)
            ->assertDontSee('Meine Anmeldungen')
            ->assertHeaderMissing('X-Frame-Options');
        $this->assertStringContainsString("frame-ancestors 'self' https://www.dpsg-trier.de https://dpsg-trier.de;", (string) $antwort->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('unsicher', (string) $antwort->headers->get('Content-Security-Policy'));

        // Nach dem Absenden zurück in die Einbett-Ansicht, mit Hinweis auf die Mail.
        $this->post('/veranstaltungen/aktion/anmeldung', self::anmeldung(['einbetten' => '1']))
            ->assertRedirect(route('events.embed', 'aktion'));
        $this->get('/veranstaltungen/aktion/einbetten')->assertSee('Fast geschafft!');
        $this->assertSame(1, EventRegistration::query()->count());

        // Ohne Freigabe zählt „einbetten“ nicht.
        $this->post('/veranstaltungen/intern/anmeldung', self::anmeldung(['einbetten' => '1', 'email' => 'ben@example.org']))
            ->assertRedirect(route('events.show', 'intern').'#anmeldung');
    }

    #[TestDox('Geschlossenes Testsystem: freigegebene Veranstaltung, Bestätigungslink, Datenschutz und Impressum öffentlich, ohne Band')]
    public function test_testsystem(): void
    {
        Mail::fake();
        config(['kluft.testsystem' => 'geschlossen']);
        Page::query()->create(['slug' => 'datenschutz', 'title' => 'Datenschutz', 'body' => '<p>Datenschutz</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->event('intern', false);

        $this->get('/datenschutz')->assertRedirect(url('/admin/login'));

        $this->event('aktion', true);
        $this->get('/veranstaltungen/intern')->assertRedirect(url('/admin/login'));
        $this->get('/veranstaltungen/aktion')->assertOk()->assertDontSee('Testsystem.')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/veranstaltungen/aktion/einbetten')->assertOk();
        $this->get('/datenschutz')->assertOk()->assertDontSee('Testsystem.');
        $this->get('/veranstaltungen')->assertRedirect(url('/admin/login'));

        $this->post('/veranstaltungen/aktion/anmeldung', self::anmeldung())->assertSessionHasNoErrors();
        $token = null;
        Mail::assertQueued(EventRegistrationMail::class, function (EventRegistrationMail $mail) use (&$token) {
            $token = $mail->token;

            return $mail->kind === 'doi';
        });
        $this->get(route('registrations.confirm', $token))->assertOk();
        $this->post(route('registrations.confirm.submit', $token))->assertOk();
        $this->assertSame('confirmed', EventRegistration::query()->sole()->status);
        $this->get(route('registrations.confirm', 'falsch'))->assertRedirect(url('/admin/login'));
    }

    #[TestDox('Mails zu freigegebenen Veranstaltungen gehen auch auf dem Testsystem an die echte Adresse, alle anderen an die Testadresse')]
    public function test_mails(): void
    {
        config(['mail.default' => 'array', 'mail.test_redirect_to' => 'test@example.org']);
        $frei = EventRegistration::query()->create(['event_id' => $this->event('aktion', true)->id, 'first_name' => 'Kim', 'last_name' => 'L', 'email' => 'kim@example.org', 'consent_text_version' => 'v1', 'consent_at' => now()]);
        $intern = EventRegistration::query()->create(['event_id' => $this->event('intern', false)->id, 'first_name' => 'Ben', 'last_name' => 'B', 'email' => 'ben@example.org', 'consent_text_version' => 'v1', 'consent_at' => now()]);

        Mail::to('kim@example.org')->sendNow(new EventRegistrationMail($frei, 'doi', 'abc'));
        Mail::to('ben@example.org')->sendNow(new EventRegistrationMail($intern, 'doi', 'abc'));
        $nachrichten = array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(app('mailer')->getSymfonyTransport()->messages()));
        $an = array_map(fn (Email $e) => array_map(fn ($a) => $a->getAddress(), $e->getTo()), $nachrichten);

        $this->assertSame([['kim@example.org'], ['test@example.org']], $an);
        $this->assertFalse($nachrichten[0]->getHeaders()->has(TestRecipientFilter::EXTERN), 'Kopfzeile wird vor dem Versand entfernt');
    }
}
