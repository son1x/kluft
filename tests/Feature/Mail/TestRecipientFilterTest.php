<?php

namespace Tests\Feature\Mail;

use App\Filament\Pages\MailQueue;
use App\Mail\ContactMessageMail;
use App\Mail\TestRecipientFilter;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Testumgebung: Mails an die Testadresse umleiten, außer an höchstens zehn
 * freigeschaltete Tester*innen (Meilenstein „Testing“).
 */
class TestRecipientFilterTest extends TestCase
{
    use RefreshDatabase;

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

    #[TestDox('Höchstens zehn Ausnahmen; ungültige Einträge zählen nicht')]
    public function test_allowlist_is_limited_to_ten(): void
    {
        $valid = array_map(fn (int $i) => "p{$i}@x.de", range(1, 11));
        config(['mail.test_allowlist' => ['kaputt', ...$valid]]);

        $this->assertSame(array_slice($valid, 0, 10), TestRecipientFilter::allowlist());
        $this->assertSame(['test@example.org'], $this->to($this->send(['p11@x.de'])));
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
        config(['mail.test_allowlist' => ['talia@example.org', '@dpsg-trier.de', 'kaputt', ...array_map(fn (int $i) => "p{$i}@x.de", range(1, 9))]]);

        $this->artisan('mail:check-allowlist')
            ->expectsOutputToContain('Einträge im Secret: 12 · im Backend ergänzt: 0 · wirksam: 10')
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

    #[TestDox('Ergänzung im Backend (Verwaltung → E-Mail-Versand, nur Admins): zusätzlich zum Secret, das Secret bleibt; Liste für Admins sichtbar')]
    public function test_ergaenzung_im_backend(): void
    {
        Filament::setCurrentPanel('admin');
        config(['mail.test_allowlist' => ['secret@example.org']]);
        $admin = User::query()->create(['name' => 'a', 'email' => 'admin@example.org', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $verwaltung = User::query()->create(['name' => 'v', 'email' => 'v@example.org', 'status' => 'active']);
        $verwaltung->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        Livewire::actingAs($verwaltung)->test(MailQueue::class)
            ->assertSee('s…@example.org')->assertDontSee('secret@example.org')->assertDontSee('Freischalten')
            ->set('neueTestAdresse', 'neu@example.org')->call('testEmpfaengerHinzufuegen')->assertForbidden();

        Livewire::actingAs($admin)->test(MailQueue::class)
            ->assertSee('secret@example.org')
            ->set('neueTestAdresse', 'keine-adresse')->call('testEmpfaengerHinzufuegen')->assertHasErrors('neueTestAdresse')
            ->set('neueTestAdresse', 'Neu@Example.org')->call('testEmpfaengerHinzufuegen')->assertHasNoErrors()
            ->assertSee('neu@example.org');

        $this->assertSame(['secret@example.org', 'neu@example.org'], TestRecipientFilter::allowlist());
        $this->assertSame(['neu@example.org'], $this->to($this->send(['neu@example.org'])));

        // Der Knopf „Entfernen“ muss gültiges JavaScript enthalten (früher stand „@js(…)“ wörtlich im Attribut).
        $html = Livewire::actingAs($admin)->test(MailQueue::class)->html();
        $this->assertStringContainsString('wire:click="testEmpfaengerEntfernen(&#039;neu@example.org&#039;)"', $html);
        $this->assertStringNotContainsString('@js(', $html);

        Livewire::actingAs($admin)->test(MailQueue::class)->call('testEmpfaengerEntfernen', 'neu@example.org');
        $this->assertSame(['secret@example.org'], TestRecipientFilter::allowlist());
    }
}
