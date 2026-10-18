<?php

namespace Tests\Feature\Privacy;

use App\Filament\Pages\PrivacyRequestsPage;
use App\Mail\ContactMessageMail;
use App\Mail\EventRegistrationMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Person;
use App\Models\User;
use App\Services\PrivacyRequests;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;
use ZipArchive;

/**
 * Betroffenenrechte pro Person (Pflichtenheft 9.2): Auskunft und Löschung
 * über die E-Mail-Adresse.
 */
class PrivacyRequestsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->event = Event::query()->create([
            'title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'status' => 'open', 'capacity' => 1, 'waitlist_enabled' => true,
            'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addDays(2),
            'form_fields' => ['diet' => 'optional'],
        ]);
    }

    private function registration(string $email, string $status = 'confirmed', array $data = []): EventRegistration
    {
        return $this->event->registrations()->create([
            'status' => $status, 'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => $email, 'data' => $data,
            'consent_at' => now(), 'consent_text_version' => 'v1', 'confirmed_at' => now(), 'purge_at' => $this->event->purgeAt(),
        ]);
    }

    private function verwaltung(): User
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $user->fresh();
    }

    #[TestDox('Auskunft: ZIP mit allen Angaben (Zusatzangaben entschlüsselt) und Dokumenten; Groß-/Kleinschreibung egal')]
    public function test_export(): void
    {
        $r = $this->registration('mia@example.org', data: ['diet' => 'vegetarisch']);
        Storage::disk('local')->put('anmeldungen/r.pdf', '%PDF-1.4 rechnung');
        $r->documents()->create(['type' => 'rechnung', 'title' => 'Rechnung 7', 'path' => 'anmeldungen/r.pdf', 'original_name' => 'r.pdf']);
        Person::query()->create(['gremium' => 'buero', 'name' => 'Mia Muster', 'role' => 'Referentin', 'email' => 'Mia@Example.org', 'consent' => true]);
        $this->registration('andere@example.org');

        $path = app(PrivacyRequests::class)->export(' MIA@example.org ');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $json = json_decode((string) $zip->getFromName('auskunft.json'), true);

        $this->assertSame('mia@example.org', $json['e_mail']);
        $this->assertCount(1, $json['veranstaltungsanmeldungen']);
        $this->assertSame('Pfingstlager', $json['veranstaltungsanmeldungen'][0]['veranstaltung']);
        $this->assertSame(['Verpflegung' => 'Vegetarisch'], $json['veranstaltungsanmeldungen'][0]['angaben']);
        $this->assertSame('Referentin', $json['personenvorstellung'][0]['funktion']);
        $document = $json['veranstaltungsanmeldungen'][0]['dokumente'][0];
        $this->assertSame('%PDF-1.4 rechnung', $zip->getFromName($document));
        $zip->close();
    }

    #[TestDox('Löschen entfernt Anmeldungen samt Dateien, Personenvorstellung und wartende Mails; Warteliste rückt nach')]
    public function test_delete(): void
    {
        Mail::fake();
        $mine = $this->registration('mia@example.org');
        Storage::disk('local')->put('anmeldungen/r.pdf', '%PDF');
        $mine->documents()->create(['type' => 'rechnung', 'path' => 'anmeldungen/r.pdf', 'original_name' => 'r.pdf']);
        $waiting = $this->registration('ben@example.org', 'waitlist');
        Person::query()->create(['gremium' => 'buero', 'name' => 'Mia', 'role' => 'X', 'email' => 'mia@example.org', 'consent' => true]);

        config(['queue.default' => 'database']);
        Mail::to('mia@example.org')->queue(new ContactMessageMail('A', 'B', 'b@example.org', null, 'Hallo zusammen'));
        Mail::to('ben@example.org')->queue(new ContactMessageMail('A', 'B', 'b@example.org', null, 'Hallo zusammen'));

        $result = app(PrivacyRequests::class)->delete('mia@example.org');

        $this->assertSame(['registrations' => 1, 'people' => 1, 'messages' => 0, 'queued' => 0], $result);
        $this->assertSame(0, EventRegistration::query()->where('email', 'mia@example.org')->count());
        Storage::disk('local')->assertMissing('anmeldungen/r.pdf');
        $this->assertSame(0, Person::query()->count());
        $this->assertSame('confirmed', $waiting->fresh()->status);
        Mail::assertQueued(EventRegistrationMail::class, fn ($m) => $m->kind === 'promoted' && $m->hasTo('ben@example.org'));
    }

    #[TestDox('Wartende Mails an die Adresse werden gefunden und gelöscht, andere bleiben')]
    public function test_queued_mails(): void
    {
        config(['queue.default' => 'database']);
        Mail::to('mia@example.org')->send(new ContactMessageMail('A', 'B', 'b@example.org', null, 'Hallo zusammen'));
        Mail::to('ben@example.org')->send(new ContactMessageMail('A', 'B', 'b@example.org', null, 'Hallo zusammen'));

        $this->assertSame(1, app(PrivacyRequests::class)->find('mia@example.org')['queued']);
        $this->assertSame(1, app(PrivacyRequests::class)->delete('mia@example.org')['queued']);
        $this->assertSame(1, DB::table('jobs')->count());
    }

    #[TestDox('Backend-Seite: nur Verwaltung/Admin; Suche zeigt Treffer; Audit-Log ohne Adresse')]
    public function test_page(): void
    {
        $this->registration('mia@example.org');
        $autor = User::query()->create(['name' => 'Autor', 'email' => 'autor@dpsg-trier.de', 'status' => 'active']);
        $autor->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own']);
        $this->actingAs($autor)->get(PrivacyRequestsPage::getUrl(panel: 'admin'))->assertForbidden();

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());
        Livewire::test(PrivacyRequestsPage::class)
            ->set('email', 'Mia@example.org')
            ->call('search')
            ->assertSee('Pfingstlager')
            ->assertSee('Mia Muster')
            ->callAction('delete')
            ->assertNotified();

        $this->assertSame(0, EventRegistration::query()->count());
        $actions = AuditLog::query()->pluck('action')->implode(' ');
        $this->assertStringContainsString('privacy.search', $actions);
        $this->assertStringContainsString('privacy.delete:1+0+0', $actions);
        $this->assertStringNotContainsString('mia@', $actions);
    }
}
