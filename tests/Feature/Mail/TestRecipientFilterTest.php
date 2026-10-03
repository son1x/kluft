<?php

namespace Tests\Feature\Mail;

use App\Mail\ContactMessageMail;
use App\Mail\TestRecipientFilter;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Testumgebung: Mails an die Testadresse umleiten, außer an höchstens fünf
 * freigeschaltete Tester*innen (Meilenstein „Testing“).
 */
class TestRecipientFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array', 'mail.test_redirect_to' => 'test@example.org']);
    }

    private function send(array $to): Email
    {
        Mail::to($to)->send(new ContactMessageMail('Allgemein', 'Erika', 'erika@example.org', 'Betreff', 'Hallo zusammen, eine Frage.'));
        $messages = iterator_to_array(app('mailer')->getSymfonyTransport()->messages());

        return end($messages)->getOriginalMessage();
    }

    /** @return list<string> */
    private function to(Email $email): array
    {
        return array_map(fn ($a) => $a->getAddress(), $email->getTo());
    }

    #[TestDox('Ohne Freigabe geht jede Mail an die Testadresse; die ursprünglichen Empfänger stehen im Kopf')]
    public function test_redirects_to_test_address(): void
    {
        $email = $this->send(['referent@dpsg-trier.de']);

        $this->assertSame(['test@example.org'], $this->to($email));
        $this->assertSame('referent@dpsg-trier.de', $email->getHeaders()->get('X-Test-Umgeleitet-Von')->getBodyAsString());
    }

    #[TestDox('Freigeschaltete Tester*innen bekommen ihre Mail direkt (Groß-/Kleinschreibung egal)')]
    public function test_allowlisted_recipient_gets_mail(): void
    {
        config(['mail.test_allowlist' => ['Tester@Example.org']]);

        $email = $this->send(['tester@example.org']);
        $this->assertSame(['tester@example.org'], $this->to($email));
        $this->assertNull($email->getHeaders()->get('X-Test-Umgeleitet-Von'));

        // Gemischt: Freigeschaltete behalten ihre Mail, der Rest geht an die Testadresse.
        $email = $this->send(['tester@example.org', 'andere@dpsg-trier.de']);
        $this->assertSame(['tester@example.org', 'test@example.org'], $this->to($email));
    }

    #[TestDox('Höchstens fünf Ausnahmen; ungültige Einträge zählen nicht')]
    public function test_allowlist_is_limited_to_five(): void
    {
        config(['mail.test_allowlist' => ['kaputt', 'a@x.de', 'b@x.de', 'c@x.de', 'd@x.de', 'e@x.de', 'f@x.de']]);

        $this->assertSame(['a@x.de', 'b@x.de', 'c@x.de', 'd@x.de', 'e@x.de'], TestRecipientFilter::allowlist());
        $this->assertSame(['test@example.org'], $this->to($this->send(['f@x.de'])));
    }

    #[TestDox('Ganze Domain freischalten („@dpsg-trier.de“ oder „*@dpsg-trier.de“); Subdomains und ähnliche Namen zählen nicht')]
    public function test_domain_entries(): void
    {
        config(['mail.test_allowlist' => ['*@DPSG-Trier.de', '@kaputt', 'tester@example.org']]);

        $this->assertSame(['@dpsg-trier.de', 'tester@example.org'], TestRecipientFilter::allowlist());
        $this->assertSame(['Referent@dpsg-trier.de'], $this->to($this->send(['Referent@dpsg-trier.de'])));

        $email = $this->send(['referent@dpsg-trier.de', 'x@mail.dpsg-trier.de', 'y@nicht-dpsg-trier.de']);
        $this->assertSame(['referent@dpsg-trier.de', 'test@example.org'], $this->to($email));
        $this->assertSame('x@mail.dpsg-trier.de, y@nicht-dpsg-trier.de', $email->getHeaders()->get('X-Test-Umgeleitet-Von')->getBodyAsString());
    }

    #[TestDox('mail:check-allowlist zeigt wirksame Einträge gekürzt und warnt bei ignorierten')]
    public function test_check_command(): void
    {
        config(['mail.test_allowlist' => ['talia@example.org', '@dpsg-trier.de', 'kaputt', 'b@x.de', 'c@x.de', 'd@x.de', 'e@x.de']]);

        $this->artisan('mail:check-allowlist')
            ->expectsOutputToContain('Einträge im Secret: 7 · davon wirksam: 5')
            ->expectsOutputToContain('1. t…@example.org')
            ->expectsOutputToContain('2. ganze Domain @dpsg-trier.de')
            ->expectsOutputToContain('2 Eintrag/Einträge werden ignoriert')
            ->doesntExpectOutputToContain('talia@')
            ->assertSuccessful();
    }

    #[TestDox('Ohne Testadresse (Produktion) bleiben alle Empfänger unverändert')]
    public function test_production_is_untouched(): void
    {
        config(['mail.test_redirect_to' => null]);

        $this->assertSame(['referent@dpsg-trier.de'], $this->to($this->send(['referent@dpsg-trier.de'])));
    }
}
