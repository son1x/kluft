<?php

namespace Tests\Feature\Content;

use App\Models\ContactMessage;
use App\Models\ContactTopic;
use App\Models\Page;
use App\Support\Arbeitsnachweis;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\LoestArbeitsnachweis;
use Tests\TestCase;

/**
 * Arbeitsnachweis der Nachrichtenformulare (App\Support\Arbeitsnachweis,
 * docs/nachrichten.md): ALTCHA v3, selbst gehostet. Ohne gültigen Nachweis
 * wird die Nachricht als Spamverdacht zurückgehalten, nicht verworfen.
 */
class ArbeitsnachweisTest extends TestCase
{
    use LoestArbeitsnachweis;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Features::setzen(Features::NACHRICHTEN, true);
        Page::query()->create(['title' => 'Kontakt', 'slug' => 'kontakt', 'body' => '<p>Büro</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        ContactTopic::query()->create(['label' => 'Allgemeine Frage', 'slug' => 'allgemein', 'email' => 'buero@dpsg-trier.de', 'sort' => 10]);
    }

    private function senden(array $input = []): void
    {
        $this->post('/kontakt', [
            'thema' => 'allgemein', 'name' => 'Erika Muster', 'email' => 'erika@example.org',
            'nachricht' => 'Gibt es eine Gruppe für Wölflinge?',
            '_t' => Crypt::encryptString((string) now()->subSeconds(20)->timestamp), ...$input,
        ])->assertSessionHas('kontakt_status', 'gesendet');
    }

    /** @return array<string, mixed> */
    private function entpackt(string $payload): array
    {
        return json_decode(base64_decode($payload), true);
    }

    private function verpackt(array $daten): string
    {
        return base64_encode(json_encode($daten));
    }

    #[TestDox('Aufgabe: PBKDF2/SHA-256 mit Signatur und Ablaufzeit, nicht zwischengespeichert; Formular bindet das Widget ein')]
    public function test_aufgabe_und_widget(): void
    {
        $antwort = $this->getJson('/spamschutz/aufgabe')->assertOk();
        $this->assertStringContainsString('no-store', (string) $antwort->headers->get('Cache-Control'));
        $p = $antwort->json('parameters');
        $this->assertSame(['algorithm', 'cost', 'expiresAt', 'keyLength', 'keyPrefix', 'nonce', 'salt'], array_keys($p));
        $this->assertSame('PBKDF2/SHA-256', $p['algorithm']);
        $this->assertSame(5000, $p['cost']);
        $this->assertSame('00', $p['keyPrefix']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $antwort->json('signature'));
        $this->assertNotSame($p['nonce'], $this->getJson('/spamschutz/aufgabe')->json('parameters.nonce'));

        $this->get('/kontakt')->assertOk()->assertSee('<altcha-widget challenge="'.route('spamschutz.aufgabe').'"', false);
    }

    #[TestDox('Gültiger Nachweis: Nachricht geht durch; derselbe Nachweis ein zweites Mal gilt nicht')]
    public function test_gueltig_und_nur_einmal(): void
    {
        $nachweis = $this->arbeitsnachweis();
        $this->assertTrue(Arbeitsnachweis::gueltig($nachweis));
        $this->assertFalse(Arbeitsnachweis::gueltig($nachweis));

        $this->senden(['altcha' => $this->arbeitsnachweis()]);
        Mail::assertQueuedCount(1);
        $this->assertSame('neu', ContactMessage::query()->sole()->status);
    }

    #[TestDox('Manipulierte oder fehlende Nachweise gelten nicht (Signatur, Kosten, Ablauf, Schlüssel, Unsinn)')]
    public function test_manipuliert(): void
    {
        $falsch = function (callable $aendern): string {
            $daten = $this->entpackt($this->arbeitsnachweis());
            $aendern($daten);

            return $this->verpackt($daten);
        };

        $this->assertFalse(Arbeitsnachweis::gueltig(null));
        $this->assertFalse(Arbeitsnachweis::gueltig(''));
        $this->assertFalse(Arbeitsnachweis::gueltig('kein-base64-json'));
        $this->assertFalse(Arbeitsnachweis::gueltig(base64_encode('{"challenge":null,"solution":null,"test":true}')));
        $this->assertFalse(Arbeitsnachweis::gueltig($falsch(fn (array &$d) => $d['challenge']['signature'] = str_repeat('0', 64))));
        // Leichtere Aufgabe vortäuschen: Signatur passt nicht mehr.
        $this->assertFalse(Arbeitsnachweis::gueltig($falsch(fn (array &$d) => $d['challenge']['parameters']['keyPrefix'] = '')));
        $this->assertFalse(Arbeitsnachweis::gueltig($falsch(fn (array &$d) => $d['solution']['derivedKey'] = str_repeat('0', 64))));
        $this->assertFalse(Arbeitsnachweis::gueltig($falsch(fn (array &$d) => $d['solution']['counter']++)));
        $this->assertFalse(Arbeitsnachweis::gueltig($falsch(fn (array &$d) => $d['solution']['counter'] = -1)));

        $alt = $this->arbeitsnachweis();
        $this->travel(Arbeitsnachweis::GUELTIG_SEKUNDEN + 1)->seconds();
        $this->assertFalse(Arbeitsnachweis::gueltig($alt));
    }

    #[TestDox('Ohne Nachweis (z. B. ohne JavaScript): nicht verworfen, sondern als Spamverdacht zurückgehalten; mit Link beide Gründe')]
    public function test_ohne_nachweis_spamverdacht(): void
    {
        $this->senden();
        $this->senden(['altcha' => ['kein' => 'text'], 'nachricht' => 'Angebot: https://koffer.example']);

        Mail::assertNothingQueued();
        $this->assertSame(
            ['Spamverdacht: ohne Arbeitsnachweis', 'Spamverdacht: Link in der Nachricht, ohne Arbeitsnachweis'],
            ContactMessage::query()->orderBy('id')->pluck('notiz')->all(),
        );
    }
}
