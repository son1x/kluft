<?php

namespace Tests\Feature\Content;

use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Person;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Gremiumsseite (z. B. Diözesanleitung): zusätzliche Personen anderer
 * Gremien („auch“) und Kontaktformular an eine Person oder alle.
 */
class GremiumKontaktTest extends TestCase
{
    use RefreshDatabase;

    private Person $vorstand;

    private Person $referentin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['dpsg.gremien' => [
            'leitung' => ['label' => 'Diözesanleitung', 'page' => 'leitungsseite', 'formular' => true,
                'auch' => [['gremium' => 'buero', 'subgroup' => 'Bildungsreferent*innen']]],
            'buero' => ['label' => 'Diözesanbüro', 'page' => 'bueroseite'],
        ]]);
        Page::query()->create(['slug' => 'leitungsseite', 'title' => 'Leitung', 'body' => '<p>Text</p>', 'status' => 'published', 'published_at' => now()]);
        Page::query()->create(['slug' => 'bueroseite', 'title' => 'Büro', 'body' => '<p>Text</p>', 'status' => 'published', 'published_at' => now()]);
        $this->vorstand = Person::query()->create(['gremium' => 'leitung', 'subgroup' => 'Vorstand', 'name' => 'Vera Vorstand', 'role' => 'Vorsitzende', 'email' => 'vera@example.org', 'consent' => true]);
        $this->referentin = Person::query()->create(['gremium' => 'buero', 'subgroup' => 'Bildungsreferent*innen', 'name' => 'Bea Bildung', 'role' => 'Bildungsreferentin', 'email' => 'bea@example.org', 'consent' => true]);
        Person::query()->create(['gremium' => 'buero', 'subgroup' => 'Verwaltung', 'name' => 'Vito Verwaltung', 'email' => 'vito@example.org', 'consent' => true]);
    }

    private function senden(string $an, array $input = []): TestResponse
    {
        return $this->from('/leitungsseite')->post('/gremium/leitung/nachricht', [
            'an' => $an, 'name' => 'Erika Muster', 'email' => 'erika@example.org', 'betreff' => 'Frage',
            'nachricht' => 'Wie kann ich mitarbeiten?', '_t' => Crypt::encryptString((string) now()->subSeconds(20)->timestamp), ...$input,
        ]);
    }

    #[TestDox('Leitungsseite zeigt auch die Bildungsreferent*innen aus dem Büro, aber nicht die Verwaltung; darunter das Formular')]
    public function test_seite(): void
    {
        $this->get('/leitungsseite')->assertOk()
            ->assertSeeInOrder(['Vera Vorstand', 'Bildungsreferent*innen', 'Bea Bildung', 'Kontakt'])
            ->assertDontSee('Vito Verwaltung')
            ->assertSee('Alle – Diözesanleitung')
            ->assertSee('value="'.$this->referentin->id.'"', false);

        // Im Büro stehen sie weiterhin, ohne Formular.
        $this->get('/bueroseite')->assertOk()->assertSee('Bea Bildung')->assertSee('Vito Verwaltung')->assertDontSee('gremium-kontakt');
    }

    #[TestDox('An alle: Mail an jede Adresse der Seite, Kopie im Eingang')]
    public function test_an_alle(): void
    {
        Features::setzen(Features::NACHRICHTEN, true);

        $this->senden('alle')->assertRedirect(url('/leitungsseite').'#gremium-kontakt')->assertSessionHas('gremium_status', 'gesendet');
        Mail::assertQueued(ContactMessageMail::class, fn ($mail) => $mail->hasTo('vera@example.org') && $mail->hasTo('bea@example.org') && ! $mail->hasTo('vito@example.org'));

        $nachricht = ContactMessage::query()->sole();
        $this->assertSame(['gremium', 'An alle: Diözesanleitung'], [$nachricht->art, $nachricht->thema]);
        $this->assertSame(['vera@example.org', 'bea@example.org'], $nachricht->empfaenger);
    }

    #[TestDox('An eine Person; fremde Personen-IDs und Gremien ohne Formular werden abgewiesen')]
    public function test_an_eine_person(): void
    {
        $this->senden((string) $this->referentin->id)->assertSessionHas('gremium_status', 'gesendet');
        Mail::assertQueued(ContactMessageMail::class, fn ($mail) => $mail->hasTo('bea@example.org') && ! $mail->hasTo('vera@example.org'));

        $vito = Person::query()->where('name', 'Vito Verwaltung')->value('id');
        $this->senden((string) $vito)->assertSessionHasErrorsIn('gremium', 'an');
        $this->post('/gremium/buero/nachricht', ['an' => 'alle'])->assertNotFound();
        $this->post('/gremium/unbekannt/nachricht', ['an' => 'alle'])->assertNotFound();
    }
}
