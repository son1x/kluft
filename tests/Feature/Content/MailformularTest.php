<?php

namespace Tests\Feature\Content;

use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\Person;
use App\Support\Features;
use App\Support\Mailformular;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Funktion „E-Mail-Adressen öffnen ein Formular“: Formular an genau eine
 * Adresse statt mailto, Kopie im Nachrichten-Eingang.
 */
class MailformularTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['dpsg.person_email_domain' => 'beispiel-stamm.de', 'dpsg.contact.email' => 'kontakt@beispiel-stamm.de']);
    }

    private function senden(string $an, array $input = []): TestResponse
    {
        return $this->post('/nachricht', [
            'an' => $an, 'name' => 'Erika Muster', 'email' => 'erika@example.org', 'betreff' => 'Gruppenstunde',
            'nachricht' => 'Darf mein Kind mal reinschnuppern?', '_t' => Crypt::encryptString((string) now()->subSeconds(20)->timestamp), ...$input,
        ]);
    }

    #[TestDox('Ausgeschaltet: mailto wie bisher, Formular gibt es nicht')]
    public function test_aus(): void
    {
        Features::setzen(Features::MAILFORMULAR, false);

        $this->assertSame('mailto:lea@beispiel-stamm.de', Mailformular::href('lea@beispiel-stamm.de'));
        $this->get('/nachricht?an='.Mailformular::schluessel('lea@beispiel-stamm.de'))->assertNotFound();
        $this->assertSame('<a href="mailto:lea@beispiel-stamm.de">x</a>', Mailformular::html('<a href="mailto:lea@beispiel-stamm.de">x</a>'));
    }

    #[TestDox('Eingeschaltet: Formular an die Person, Mail an sie und Kopie im Eingang')]
    public function test_formular_mail_und_kopie(): void
    {
        Features::setzen(Features::MAILFORMULAR, true);
        Features::setzen(Features::NACHRICHTEN, true);
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Lea Leitend', 'role' => 'Stammesvorständin', 'email' => 'lea@beispiel-stamm.de', 'consent' => true]);

        $href = Mailformular::href('Lea@beispiel-stamm.de');
        $this->assertStringContainsString('/nachricht?an=', $href);
        $an = Mailformular::schluessel('lea@beispiel-stamm.de');

        $this->get('/nachricht?an='.$an)->assertOk()
            ->assertSee('Nachricht an Lea Leitend')->assertSee('Stammesvorständin')
            ->assertSee('mailto:lea@beispiel-stamm.de', false);

        $this->senden($an)->assertSessionHas('kontakt_status', 'gesendet');
        Mail::assertQueued(ContactMessageMail::class, fn ($mail) => $mail->hasTo('lea@beispiel-stamm.de'));

        $nachricht = ContactMessage::query()->sole();
        $this->assertSame('direkt', $nachricht->art);
        $this->assertSame('An Lea Leitend', $nachricht->thema);
        $this->assertSame(['lea@beispiel-stamm.de'], $nachricht->empfaenger);
    }

    #[TestDox('Nur signierte Adressen: verfälschter Link führt ins Leere')]
    public function test_nur_signierte_adressen(): void
    {
        Features::setzen(Features::MAILFORMULAR, true);
        $echt = Mailformular::schluessel('lea@beispiel-stamm.de');
        [, $sig] = explode('.', $echt);
        $falsch = rtrim(strtr(base64_encode('jemand@fremd.example'), '+/', '-_'), '=').'.'.$sig;

        $this->get('/nachricht?an='.$falsch)->assertNotFound();
        $this->get('/nachricht')->assertNotFound();
        $this->senden($falsch)->assertNotFound();
        Mail::assertNothingQueued();
    }

    #[TestDox('Seitentext: mailto auf eigene Domain wird zum Formular, fremde Adressen bleiben')]
    public function test_seitentext(): void
    {
        Features::setzen(Features::MAILFORMULAR, true);

        $html = Mailformular::html('<p><a href="mailto:kontakt@beispiel-stamm.de">Schreib uns</a> oder <a href="mailto:info@ruesthaus.example?subject=X">Rüsthaus</a></p>');

        $this->assertStringContainsString('/nachricht?an=', $html);
        $this->assertStringContainsString('href="mailto:info@ruesthaus.example?subject=X"', $html);
        $this->assertStringContainsString('>Schreib uns</a>', $html);
    }
}
