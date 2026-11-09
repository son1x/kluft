<?php

namespace Tests\Feature\Events;

use App\Mail\TestRecipientFilter;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\Wartung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Ganzer Weg der Bestätigungsmails für die Weihnachtspostkartenaktion auf
 * dem geschlossenen Testsystem (vor dem Livegang, eingebettet in die alte
 * Website): Anmeldung über die Einbett-Ansicht, Mail über die Warteschlange
 * an die echte Adresse, Bestätigen und Stornieren per Link aus der Mail.
 */
class WeihnachtsaktionMailsTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'weihnachtspostkartenaktion-2026';

    /** @return list<Email> */
    private function alle(): array
    {
        return array_values(array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(app('mailer')->getSymfonyTransport()->messages())));
    }

    /** Mails an die angemeldete Person (ohne die Info-Mails an das Team). @return list<Email> */
    private function gesendet(): array
    {
        return array_values(array_filter($this->alle(), fn (Email $m) => ! str_contains((string) $m->getSubject(), 'Neue Anmeldung') && ! str_starts_with((string) $m->getSubject(), 'Abmeldung:')));
    }

    /** Info-Mails an das Team: Empfänger je Mail. @return list<array{string, list<string>}> */
    private function infos(): array
    {
        return array_values(array_map(fn (Email $m) => [(string) $m->getSubject(), array_map(fn ($a) => $a->getAddress(), $m->getTo())],
            array_filter($this->alle(), fn (Email $m) => str_starts_with((string) $m->getSubject(), 'Neue Anmeldung:') || str_starts_with((string) $m->getSubject(), 'Abmeldung:'))));
    }

    private static function link(Email $mail, string $art): string
    {
        $text = (string) $mail->getTextBody().' '.(string) $mail->getHtmlBody();
        self::assertSame(1, preg_match('#https?://[^\s"<>]+/anmeldung/'.$art.'/[A-Za-z0-9]+#', html_entity_decode($text), $treffer), "Link „{$art}“ in der Mail");

        return $treffer[0];
    }

    #[TestDox('Geschlossenes Testsystem mit Umleitung: Bitte-bestätigen-, Bestätigungs- und Storno-Mail gehen an die echte Adresse, Links funktionieren ohne Anmeldung')]
    public function test_bestaetigungsmails(): void
    {
        $this->travelTo('2026-11-10 12:00');
        $anlegen = require base_path('instanzen/dv-trier/migrations/2026_11_09_160000_weihnachtspostkartenaktion_2026.php');
        $anlegen->auchInTests = true;
        $anlegen->up();
        (require base_path('instanzen/dv-trier/migrations/2026_11_09_170100_weihnachtspostkartenaktion_vorab_freigeben.php'))->up();
        config(['dpsg.contact.email' => 'info@example.org', 'dpsg.person_email_domain' => 'dpsg-trier.de', 'dpsg.person_email_schema' => null]);
        (require base_path('instanzen/dv-trier/migrations/2026_11_10_090100_weihnachtspostkartenaktion_verantwortlich.php'))->up();
        $this->assertTrue(Event::query()->where('slug', self::SLUG)->sole()->extern_freigegeben);

        config(['kluft.testsystem' => 'geschlossen', 'mail.default' => 'array', 'mail.test_redirect_to' => 'test@example.org']);

        // Anmeldung aus dem iframe: zurück in die Einbett-Ansicht.
        $this->post('/veranstaltungen/'.self::SLUG.'/anmeldung', [
            'einbetten' => '1',
            'first_name' => 'Kim', 'last_name' => 'Leitung', 'email' => 'kim@example.org', 'consent' => '1',
            'stamm' => 'Stamm Sankt Georg', 'eigen_anzahlwb' => '18', 'eigen_adresse' => "Hauptstraße 1\n54290 Trier",
            'eigen_aktion' => ['Weihnachtskarte für einen Stamm im Diözesanverband gestalten', 'Video aufnehmen oder Weihnachtskarte gestalten für einen Stamm in Bolivien'],
            'eigen_vorlage' => 'Ich drucke die Weihnachtskarte selbst aus',
        ])->assertSessionHasNoErrors()->assertRedirect(route('events.embed', self::SLUG));

        $mails = $this->gesendet();
        $this->assertCount(1, $mails);
        $this->assertSame(['kim@example.org'], array_map(fn ($a) => $a->getAddress(), $mails[0]->getTo()));
        $this->assertStringStartsWith('Bitte bestätige deine Anmeldung: Wö- und Biber-Weihnachtspostkartenaktion', (string) $mails[0]->getSubject());
        $this->assertFalse($mails[0]->getHeaders()->has(TestRecipientFilter::EXTERN));

        // Link aus der Mail, ohne Anmeldung (geschlossenes Testsystem).
        $bestaetigen = self::link($mails[0], 'bestaetigen');
        $this->get($bestaetigen)->assertOk();
        $this->post($bestaetigen)->assertOk();
        $this->assertSame('confirmed', EventRegistration::query()->sole()->status);

        $mails = $this->gesendet();
        $this->assertCount(2, $mails);
        $this->assertSame(['kim@example.org'], array_map(fn ($a) => $a->getAddress(), $mails[1]->getTo()));
        $this->assertStringStartsWith('Anmeldung bestätigt:', (string) $mails[1]->getSubject());

        $stornieren = self::link($mails[1], 'stornieren');
        $this->get($stornieren)->assertOk();
        $this->post($stornieren)->assertOk();
        $this->assertSame('cancelled', EventRegistration::query()->sole()->status);

        $mails = $this->gesendet();
        $this->assertCount(3, $mails);
        $this->assertSame(['kim@example.org'], array_map(fn ($a) => $a->getAddress(), $mails[2]->getTo()));
        $this->assertStringStartsWith('Anmeldung storniert:', (string) $mails[2]->getSubject());

        // Info an Sammeladresse und Verantwortliche (Lisa Alm), auch auf dem Testsystem echt zugestellt.
        $titel = 'Wö- und Biber-Weihnachtspostkartenaktion 🎄';
        $this->assertSame([
            ['Neue Anmeldung: '.$titel, ['info@example.org']],
            ['Neue Anmeldung: '.$titel, ['l.alm@dpsg-trier.de']],
            ['Abmeldung: '.$titel, ['info@example.org']],
            ['Abmeldung: '.$titel, ['l.alm@dpsg-trier.de']],
        ], $this->infos());
    }

    #[TestDox('Livetag: Test im Wartungsmodus, der Bestätigungslink aus der Mail funktioniert weiter, Seite und Formular nicht')]
    public function test_bestaetigen_im_wartungsmodus(): void
    {
        $this->travelTo('2026-11-20 20:00');
        $anlegen = require base_path('instanzen/dv-trier/migrations/2026_11_09_160000_weihnachtspostkartenaktion_2026.php');
        $anlegen->auchInTests = true;
        $anlegen->up();
        (require base_path('instanzen/dv-trier/migrations/2026_11_09_170100_weihnachtspostkartenaktion_vorab_freigeben.php'))->up();
        config(['kluft.testsystem' => 'geschlossen', 'mail.default' => 'array', 'mail.test_redirect_to' => 'test@example.org']);

        $this->post('/veranstaltungen/'.self::SLUG.'/anmeldung', [
            'first_name' => 'Kim', 'last_name' => 'Leitung', 'email' => 'kim@example.org', 'consent' => '1',
            'stamm' => 'Stamm Sankt Georg', 'eigen_anzahlwb' => '12', 'eigen_adresse' => "Hauptstraße 1\n54290 Trier",
            'eigen_aktion' => ['Weihnachtskarte für einen Stamm im Diözesanverband gestalten'], 'eigen_vorlage' => 'Per Post an die Adresse oben',
        ])->assertSessionHasNoErrors();
        $bestaetigen = self::link($this->gesendet()[0], 'bestaetigen');

        $this->travelTo('2026-11-21 10:00');
        Wartung::an('Wir ziehen um.', null, true);
        $this->get('/veranstaltungen/'.self::SLUG)->assertStatus(503);
        $this->get('/veranstaltungen/'.self::SLUG.'/einbetten')->assertStatus(503);
        $this->get('/anmeldung/bestaetigen/falsch')->assertRedirect(url('/admin/login'));

        $this->get($bestaetigen)->assertOk();
        $this->post($bestaetigen)->assertOk();
        $this->assertSame('confirmed', EventRegistration::query()->sole()->status);
        $this->assertStringStartsWith('Anmeldung bestätigt:', (string) $this->gesendet()[1]->getSubject());
    }
}
