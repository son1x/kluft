<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\ContactTopic;
use App\Models\Event;
use App\Models\Page;
use App\Models\User;
use App\Services\PrivacyRequests;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Nachrichten-Eingang (docs/nachrichten.md): Kontaktnachrichten gehen per
 * Mail an die zuständige Person und liegen zusätzlich zentral im Backend,
 * sichtbar für Verwaltung und Admins.
 */
class NachrichtenEingangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Filament::setCurrentPanel('admin');
        Features::setzen(Features::NACHRICHTEN, true);
        Page::query()->create(['title' => 'Kontakt', 'slug' => 'kontakt', 'body' => '<p>Büro</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        ContactTopic::query()->create(['label' => 'Fachbereich: Bolivienpartnerschaft', 'slug' => 'bolivien', 'email' => 'bolivien@example.org', 'sort' => 10]);
    }

    private function token(): string
    {
        return Crypt::encryptString((string) now()->subSeconds(20)->timestamp);
    }

    private function senden(array $input = []): void
    {
        $this->post('/kontakt', [
            'thema' => 'bolivien', 'name' => 'Erika Muster', 'email' => 'Erika@Example.org', 'betreff' => 'Partnerschaft',
            'nachricht' => 'Wie kann unser Stamm mitmachen? 4711', '_t' => $this->token(), ...$input,
        ])->assertSessionHas('kontakt_status', 'gesendet');
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => $role, 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global', 'scope_key' => null]);

        return $user->fresh();
    }

    #[TestDox('Nachricht geht per Mail an den Empfänger und liegt verschlüsselt im Eingang; Formular weist auf die Speicherung hin')]
    public function test_mail_und_eingang(): void
    {
        $this->get('/kontakt')->assertSee('höchstens 6 Monate');
        $this->senden();

        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('bolivien@example.org'));
        $m = ContactMessage::query()->sole();
        $this->assertSame(['kontakt', 'Fachbereich: Bolivienpartnerschaft', 'erika@example.org', 'Partnerschaft', ['bolivien@example.org'], true, 'neu'],
            [$m->art, $m->thema, $m->email, $m->betreff, $m->empfaenger, $m->zugestellt, $m->status]);
        $this->assertStringContainsString('4711', $m->nachricht);
        $this->assertStringNotContainsString('4711', (string) DB::table('contact_messages')->value('nachricht'), 'Text verschlüsselt gespeichert');
    }

    #[TestDox('Mail fehlgeschlagen: Nachricht ist trotzdem angekommen und im Eingang markiert')]
    public function test_mail_fehler(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $this->senden();

        $this->assertFalse(ContactMessage::query()->sole()->zugestellt);
    }

    #[TestDox('Frage zur Veranstaltung landet ebenfalls im Eingang, mit Veranstaltung')]
    public function test_frage_zur_veranstaltung(): void
    {
        $event = Event::query()->create([
            'title' => 'Wölflingstag', 'slug' => 'woelflingstag', 'location' => 'Trier', 'status' => 'open',
            'starts_at' => now()->addMonth()->setTime(10, 0), 'ends_at' => now()->addMonth()->setTime(17, 0), 'contact_email' => 'woelflinge@example.org',
        ]);
        $this->post('/veranstaltungen/woelflingstag/frage', ['name' => 'Erika', 'email' => 'erika@example.org', 'nachricht' => 'Gibt es vegetarisches Essen?', '_t' => $this->token()]);

        $m = ContactMessage::query()->sole();
        $this->assertSame(['veranstaltung', $event->id, 'Wölflingstag', ['woelflinge@example.org']], [$m->art, $m->event_id, $m->betreff, $m->empfaenger]);
    }

    #[TestDox('Eingang nur für Verwaltung aufwärts: ansehen, erledigen mit Notiz, Zähler im Menü')]
    public function test_eingang_im_backend(): void
    {
        $this->senden();
        $m = ContactMessage::query()->sole();

        $this->actingAs($this->user('vorstand'));
        $this->assertFalse(ContactMessageResource::canViewAny());
        $this->get(ContactMessageResource::getUrl())->assertForbidden();

        $this->actingAs($this->user('verwaltung'));
        $this->assertSame('1', ContactMessageResource::getNavigationBadge());
        Livewire::test(ListContactMessages::class)
            ->assertCanSeeTableRecords([$m])
            ->assertSee('Erika Muster')
            ->mountTableAction('ansehen', $m)->assertSee('Wie kann unser Stamm mitmachen?');
        Livewire::test(ListContactMessages::class)
            ->callTableAction('erledigt', $m, ['notiz' => 'beantwortet von Lisa'])
            ->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$m]);

        $this->assertSame(['erledigt', 'beantwortet von Lisa'], [$m->fresh()->status, $m->fresh()->notiz]);
        $this->assertNull(ContactMessageResource::getNavigationBadge());

        $this->actingAs($this->user('admin'));
        $this->get(ContactMessageResource::getUrl())->assertOk();

        Features::setzen(Features::NACHRICHTEN, false);
        $this->assertFalse(ContactMessageResource::canViewAny());
    }

    #[TestDox('Löschfrist, Auskunft und Löschung nach Datenschutzanfrage')]
    public function test_frist_und_datenschutz(): void
    {
        $this->senden();
        $this->travel(7)->months();
        $this->senden(['nachricht' => 'Noch eine Frage zum Partnerschaftstag']);
        $this->artisan('kontakt:prune')->assertSuccessful();
        $this->assertSame(['Noch eine Frage zum Partnerschaftstag'], ContactMessage::query()->get()->pluck('nachricht')->all());

        $requests = app(PrivacyRequests::class);
        $this->assertCount(1, $requests->find('erika@example.org')['messages']);
        $zip = new \ZipArchive;
        $zip->open($requests->export('erika@example.org'));
        $this->assertStringContainsString('Partnerschaftstag', (string) $zip->getFromName('auskunft.json'));
        $this->assertSame(1, $requests->delete('erika@example.org')['messages']);
        $this->assertSame(0, ContactMessage::query()->count());
    }
}
