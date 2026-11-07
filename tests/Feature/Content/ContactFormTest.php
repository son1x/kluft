<?php

namespace Tests\Feature\Content;

use App\Mail\ContactMessageMail;
use App\Models\AuditLog;
use App\Models\ContactTopic;
use App\Models\Event;
use App\Models\Page;
use App\Models\Person;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\LoestArbeitsnachweis;
use Tests\TestCase;

/**
 * Kontaktformular und Frage zur Veranstaltung (F-CMS-09): nur Mailversand,
 * keine Speicherung, Spamschutz per Honeypot, Zeitfalle (mindestens 5 Sekunden,
 * höchstens 2 Stunden, jeder Zeitstempel nur einmal) und Rate-Limit.
 * Mit Nachrichten-Eingang: NachrichtenEingangTest.
 */
class ContactFormTest extends TestCase
{
    use LoestArbeitsnachweis;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Features::setzen(Features::NACHRICHTEN, false);
        Page::query()->create(['title' => 'Kontakt', 'slug' => 'kontakt', 'body' => '<p>Diözesanbüro, Weberbach 71</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        ContactTopic::query()->create(['label' => 'Allgemeine Frage', 'slug' => 'allgemein', 'sort' => 10]);
        ContactTopic::query()->create(['label' => 'Stufe: Wölflinge', 'slug' => 'woelflinge', 'stage' => 'woelflinge', 'sort' => 20]);
        ContactTopic::query()->create(['label' => 'Fachbereich: Bolivienpartnerschaft', 'slug' => 'bolivien', 'section' => 'bolivien', 'email' => 'bolivien@dpsg-trier.de', 'sort' => 30]);
        ContactTopic::query()->create(['label' => 'Versteckt', 'slug' => 'alt', 'active' => false]);
    }

    /** Zeitstempel, als hätte jemand das Formular vor 20 Sekunden geöffnet. */
    private function token(int $secondsAgo = 20): string
    {
        return Crypt::encryptString((string) now()->subSeconds($secondsAgo)->timestamp);
    }

    private function send(array $input = []): TestResponse
    {
        return $this->post('/kontakt', [
            'thema' => 'allgemein',
            'name' => 'Erika Muster',
            'email' => 'erika@example.org',
            'betreff' => 'Gruppenstunde',
            'nachricht' => 'Gibt es in Wittlich eine Wölflingsgruppe?',
            '_t' => $this->token(),
            'altcha' => $this->arbeitsnachweis(),
            ...$input,
        ]);
    }

    #[TestDox('Kontaktseite zeigt Text und Formular; ?thema= wählt das Thema vor, inaktive Themen fehlen')]
    public function test_page_shows_form_with_topics(): void
    {
        $this->get('/kontakt')->assertOk()
            ->assertSee('Weberbach 71')
            ->assertSee('Worum geht es?')
            ->assertSeeInOrder(['Allgemeine Frage', 'Stufe: Wölflinge', 'Fachbereich: Bolivienpartnerschaft'])
            ->assertDontSee('Versteckt')
            ->assertSee('name="_t"', false);

        $this->get('/kontakt?thema=woelflinge')->assertOk()
            ->assertSee('<option value="woelflinge" selected>', false);
    }

    #[Group('dv-trier')]

    #[TestDox('Allgemeine Fragen gehen ans Diözesanbüro; „Antworten“ geht an die Absenderin')]
    public function test_general_question_goes_to_office_with_reply_to(): void
    {
        $this->send()->assertRedirect('/kontakt#kontaktformular')->assertSessionHas('kontakt_status', 'gesendet');

        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail) {
            $mail->assertHasReplyTo('erika@example.org', 'Erika Muster');
            $mail->assertHasSubject('Allgemeine Frage: Gruppenstunde');
            $mail->assertSeeInText('Gibt es in Wittlich eine Wölflingsgruppe?');

            return $mail->hasTo('info@dpsg-trier.de') && count($mail->to) === 1;
        });
        $this->get('/kontakt')->assertSee('Danke für deine Nachricht!');
    }

    #[Group('dv-trier')]

    #[TestDox('Stufenthema geht an die Stufenreferent*innen (ohne DAK und ohne Freigabe); feste Adresse hat Vorrang')]
    public function test_stage_topic_goes_to_referents_and_fixed_address_wins(): void
    {
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Lena Lange', 'stage' => 'woelflinge', 'consent' => true]);
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Paul Peters', 'stage' => 'woelflinge', 'email' => 'woelflinge@dpsg-trier.de', 'consent' => true]);
        Person::query()->create(['gremium' => 'dak', 'name' => 'Dora Dak', 'stage' => 'woelflinge', 'email' => 'dora@example.org', 'consent' => true]);
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Nina Nein', 'stage' => 'woelflinge', 'consent' => false]);
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Rolf Rover', 'stage' => 'rover', 'consent' => true]);

        $this->send(['thema' => 'woelflinge'])->assertSessionHas('kontakt_status', 'gesendet');
        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('l.lange@dpsg-trier.de')
            && $mail->hasTo('woelflinge@dpsg-trier.de') && count($mail->to) === 2);

        $this->send(['thema' => 'bolivien']);
        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('bolivien@dpsg-trier.de') && count($mail->to) === 1);
    }

    #[Group('dv-trier')]

    #[TestDox('Ohne Ansprechpersonen geht ein Stufenthema ans Diözesanbüro')]
    public function test_topic_without_people_falls_back_to_office(): void
    {
        $this->assertSame(['info@dpsg-trier.de'], ContactTopic::query()->where('slug', 'woelflinge')->first()->recipients());
    }

    #[TestDox('Nachricht wird nicht gespeichert, nur ein Audit-Eintrag ohne Personendaten')]
    public function test_nothing_is_stored(): void
    {
        $this->send(['nachricht' => 'Geheime Nachricht mit Inhalt 4711']);

        $this->assertTrue(AuditLog::query()->where('action', 'kontakt:gesendet:allgemein')->exists());
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (DB::table($table)->get() as $row) {
                $json = json_encode($row, JSON_UNESCAPED_UNICODE);
                $this->assertStringNotContainsString('4711', $json, "Nachricht in Tabelle {$table} gefunden");
                $this->assertStringNotContainsString('erika@example.org', $json, "Adresse in Tabelle {$table} gefunden");
            }
        }
    }

    #[TestDox('Bots (Honeypot, zu schnell abgeschickt, ohne Zeitstempel) bekommen „Danke“, es geht aber keine Mail raus')]
    public function test_bots_are_silently_dropped(): void
    {
        $this->send(['website' => 'http://spam.example'])->assertSessionHas('kontakt_status', 'gesendet');
        $this->send(['_t' => $this->token(1)])->assertSessionHas('kontakt_status', 'gesendet');
        $this->send(['_t' => 'kaputt'])->assertSessionHas('kontakt_status', 'gesendet');

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    #[TestDox('Zeitfalle: unter 5 Sekunden verworfen, ab 5 Sekunden angenommen')]
    public function test_mindestzeit_fuenf_sekunden(): void
    {
        $this->send(['_t' => $this->token(4)])->assertSessionHas('kontakt_status', 'gesendet');
        Mail::assertNothingQueued();

        $this->send(['_t' => $this->token(5)])->assertSessionHas('kontakt_status', 'gesendet');
        Mail::assertQueuedCount(1);
    }

    #[TestDox('Ein Zeitstempel gilt nur einmal: wiederholtes Absenden (auch von anderen IP-Adressen) wird still verworfen')]
    public function test_zeitstempel_nur_einmal(): void
    {
        $token = $this->token();
        foreach (range(1, 4) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$i])
                ->send(['_t' => $token, 'email' => "absender{$i}@example.org"])
                ->assertSessionHas('kontakt_status', 'gesendet');
        }
        Mail::assertQueuedCount(1);

        // Ein frisch geladenes Formular geht wieder.
        $this->send(['_t' => $this->token(), 'altcha' => $this->arbeitsnachweis()])->assertSessionHas('kontakt_status', 'gesendet');
        Mail::assertQueuedCount(2);
    }

    #[TestDox('Nach einem Fehler zeigt das Formular einen frischen Zeitstempel; damit geht die korrigierte Nachricht durch')]
    public function test_nach_fehler_frischer_zeitstempel(): void
    {
        $this->from('/kontakt')->send(['email' => ''])->assertSessionHasErrors('email');
        preg_match('/name="_t" value="([^"]+)"/', $this->get('/kontakt')->getContent(), $treffer);
        $this->travel(6)->seconds();

        $this->send(['_t' => html_entity_decode($treffer[1])])->assertSessionHas('kontakt_status', 'gesendet');
        Mail::assertQueuedCount(1);
    }

    #[TestDox('Spamverdacht ohne Nachrichten-Eingang: Mail geht raus, mit „[Spamverdacht]“ im Betreff zum Filtern')]
    public function test_spamverdacht_ohne_eingang(): void
    {
        $this->send(['nachricht' => 'Exklusiver Räumungsverkauf: https://koffer.example'])->assertSessionHas('kontakt_status', 'gesendet');
        $this->send(['nachricht' => 'Gibt es eine Gruppe für Wölflinge?'])->assertSessionHas('kontakt_status', 'gesendet');

        $betreffe = Mail::queued(ContactMessageMail::class)->map(fn (ContactMessageMail $mail) => $mail->envelope()->subject)->all();
        $this->assertCount(2, $betreffe);
        $this->assertStringStartsWith('[Spamverdacht] Allgemeine Frage: ', $betreffe[0]);
        $this->assertStringStartsWith('Allgemeine Frage: ', $betreffe[1]);
    }

    #[TestDox('Ein über zwei Stunden offenes Formular wird mit Hinweis zurückgegeben, Eingaben bleiben erhalten')]
    public function test_expired_form_keeps_input(): void
    {
        $this->from('/kontakt')->send(['_t' => $this->token(2 * 3600 + 1)])
            ->assertRedirect('/kontakt')
            ->assertSessionHas('kontakt_status', 'abgelaufen')
            ->assertSessionHasInput('nachricht');
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    #[TestDox('Pflichtfelder, unbekanntes Thema, Zeilenumbrüche im Namen und Link-Spam werden abgelehnt')]
    public function test_validation(): void
    {
        $this->send(['email' => '', 'nachricht' => ''])->assertSessionHasErrors(['email', 'nachricht']);
        $this->send(['thema' => 'alt'])->assertSessionHasErrors('thema');
        $this->send(['name' => "Erika\r\nBcc: alle@example.org"])->assertSessionHasErrors('name');
        $this->send(['betreff' => "Hallo\nBcc: x@example.org"])->assertSessionHasErrors('betreff');
        $this->send(['nachricht' => 'Kauf hier: http://a.example http://b.example http://c.example http://d.example'])
            ->assertSessionHasErrors('nachricht');

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    #[TestDox('Versandfehler: freundliche Meldung, Eingaben bleiben erhalten')]
    public function test_mail_failure_is_reported(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->from('/kontakt')->send()->assertRedirect('/kontakt')
            ->assertSessionHas('kontakt_status', 'fehler')
            ->assertSessionHasInput('name', 'Erika Muster');
        $this->assertTrue(AuditLog::query()->where('action', 'kontakt:fehler:allgemein')->exists());
    }

    #[TestDox('Frage zur Veranstaltung geht an deren Kontaktperson, Fehler erscheinen nicht im Anmeldeformular')]
    public function test_event_question(): void
    {
        $event = Event::query()->create([
            'title' => 'Wölflingstag', 'slug' => 'woelflingstag', 'location' => 'Trier', 'status' => 'open',
            'starts_at' => now()->addMonth()->setTime(10, 0), 'ends_at' => now()->addMonth()->setTime(17, 0),
            'contact_name' => 'Lena Lange', 'contact_email' => 'woelflinge@dpsg-trier.de',
        ]);

        $this->get('/veranstaltungen/woelflingstag')->assertOk()->assertSee('Frage an Lena Lange schreiben');

        $this->post('/veranstaltungen/woelflingstag/frage', ['name' => 'Erika', 'email' => 'erika@example.org', 'nachricht' => 'Gibt es vegetarisches Essen?', '_t' => $this->token(), 'altcha' => $this->arbeitsnachweis()])
            ->assertRedirect('/veranstaltungen/woelflingstag#frage');
        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail) {
            $mail->assertHasSubject('Frage zur Veranstaltung: Wölflingstag');
            $mail->assertSeeInText('/veranstaltungen/woelflingstag');

            return $mail->hasTo('woelflinge@dpsg-trier.de');
        });
        $this->assertTrue(AuditLog::query()->where('action', 'veranstaltung.frage:gesendet')->where('subject_id', $event->id)->exists());

        $this->from('/veranstaltungen/woelflingstag')
            ->post('/veranstaltungen/woelflingstag/frage', ['name' => 'Erika', 'email' => '', 'nachricht' => 'Hallo, eine Frage', '_t' => $this->token(), 'altcha' => $this->arbeitsnachweis()])
            ->assertSessionHasErrorsIn('frage', ['email']);
        $this->get('/veranstaltungen/woelflingstag')->assertDontSee('Bitte prüfe deine Angaben.')->assertSee('<details class="mt-4 bg-white p-5 shadow-md"  open', false);
    }

    #[Group('dv-trier')]

    #[TestDox('Stufen- und Fachbereichsseiten verlinken das Formular mit vorausgewähltem Thema')]
    public function test_stage_and_section_pages_link_to_form(): void
    {
        Page::query()->create(['title' => 'Wölflinge', 'slug' => 'stufe-woelflinge', 'stage' => 'woelflinge', 'body' => '<p>Wölflinge</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        Page::query()->create(['title' => 'Bolivien', 'slug' => 'bolivien', 'section' => 'bolivien', 'body' => '<p>Partnerschaft</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get('/stufe-woelflinge')->assertOk()->assertSee('/kontakt?thema=woelflinge#kontaktformular', false);
        $this->get('/bolivien')->assertOk()->assertSee('/kontakt?thema=bolivien#kontaktformular', false);

        ContactTopic::query()->where('slug', 'bolivien')->update(['active' => false]);
        $this->get('/bolivien')->assertOk()->assertDontSee('?thema=bolivien', false);
    }
}
