<?php

namespace Tests\Feature\Content;

use App\Console\Commands\SpamverdachtMeldenCommand;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\Channels\NtfyChannel;
use App\Notifications\SpamverdachtNotification;
use App\Support\Features;
use App\Support\MailEvents;
use App\Support\PushEvents;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Sammelhinweis bei Spamverdacht (docs/nachrichten.md): täglich, per Mail
 * und/oder Push nach Wahl der Person, ohne Inhalt.
 */
class SpamverdachtMeldenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Features::setzen(Features::NACHRICHTEN, true);
        Features::setzen(Features::PUSH, true);
        Notification::fake();
        Cache::forget(SpamverdachtMeldenCommand::CACHE_KEY);
    }

    private function user(string $role, array $attrs = []): User
    {
        $user = User::query()->create(['name' => ucfirst($role), 'email' => $role.'@example.org', 'status' => 'active', ...$attrs]);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global', 'scope_key' => null]);

        return $user->fresh();
    }

    private function nachricht(string $status = 'spam', ?string $am = null): ContactMessage
    {
        $m = ContactMessage::query()->create([
            'art' => 'kontakt', 'thema' => 'Kontaktformular', 'name' => 'Test', 'email' => 'absender@example.org',
            'nachricht' => 'Kauf jetzt https://example.org', 'empfaenger' => ['buero@example.org'], 'zugestellt' => false, 'status' => $status,
        ]);
        if ($am) {
            $m->forceFill(['created_at' => $am])->save();
        }

        return $m;
    }

    #[TestDox('Neue Spamverdacht-Nachrichten: Verwaltung bekommt standardmäßig eine Mail, Autor*in nichts; ohne Neues kein Hinweis')]
    public function test_sammelhinweis_standard_mail(): void
    {
        $verwaltung = $this->user('verwaltung');
        $autor = $this->user('autor');
        $this->nachricht();
        $this->nachricht();
        $this->nachricht('neu');

        $this->artisan('nachrichten:spamverdacht-melden')->assertSuccessful();

        Notification::assertSentTo($verwaltung, SpamverdachtNotification::class, function (SpamverdachtNotification $n, array $kanaele) {
            return $n->neu === 2 && $n->gesamt === 2 && $kanaele === ['mail'];
        });
        Notification::assertNotSentTo($autor, SpamverdachtNotification::class);

        // Zweiter Lauf ohne neue Fälle: nichts.
        Notification::fake();
        $this->artisan('nachrichten:spamverdacht-melden')->assertSuccessful();
        Notification::assertNothingSent();
    }

    #[TestDox('Wahlweise: nur Push, Mail und Push oder gar nichts')]
    public function test_kanaele_nach_wahl(): void
    {
        if (! Features::enabled(Features::PUSH)) {
            $this->markTestSkipped('Push gibt es in dieser Instanz nicht (Ausbaustufe).');
        }
        $nurPush = $this->user('verwaltung', ['email' => 'push@example.org', 'ntfy_topic' => 'kluft-test-1', 'ntfy_events' => [PushEvents::SPAMVERDACHT], 'mail_events' => []]);
        $beides = $this->user('admin', ['ntfy_topic' => 'kluft-test-2', 'ntfy_events' => [PushEvents::SPAMVERDACHT]]);
        $aus = $this->user('vorstand', ['mail_events' => []]);
        $aus->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);
        $this->nachricht();

        $this->artisan('nachrichten:spamverdacht-melden')->assertSuccessful();

        Notification::assertSentTo($nurPush, SpamverdachtNotification::class, fn ($n, array $kanaele) => $kanaele === [NtfyChannel::class]);
        Notification::assertSentTo($beides, SpamverdachtNotification::class, fn ($n, array $kanaele) => $kanaele === ['mail', NtfyChannel::class]);
        Notification::assertNotSentTo($aus->fresh(), SpamverdachtNotification::class);
    }

    #[TestDox('Nur Neues seit dem letzten Lauf; ohne Nachrichten-Eingang kein Hinweis')]
    public function test_nur_neues_und_schalter(): void
    {
        $verwaltung = $this->user('verwaltung');
        $this->nachricht('spam', now()->subDays(3)->toDateTimeString());
        $this->artisan('nachrichten:spamverdacht-melden');
        Notification::assertNothingSent();

        Features::setzen(Features::NACHRICHTEN, false);
        $this->nachricht();
        $this->artisan('nachrichten:spamverdacht-melden');
        Notification::assertNothingSent();
        $this->assertFalse($verwaltung->wantsMail(MailEvents::SPAMVERDACHT));
    }

    #[TestDox('Hinweis enthält keine Inhalte, Namen oder Adressen und verlinkt den gefilterten Eingang')]
    public function test_inhalt_ohne_personendaten(): void
    {
        $verwaltung = $this->user('verwaltung');
        $n = new SpamverdachtNotification(2, 3, now()->addDays(20));

        $mail = $n->toMail($verwaltung);
        $text = $mail->subject.' '.implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines);
        $this->assertStringContainsString('2 neue Nachrichten mit Spamverdacht', $text);
        $this->assertStringNotContainsString('absender@example.org', $text);
        $this->assertStringContainsString('tableFilters', $mail->actionUrl);

        $push = $n->toNtfy($verwaltung);
        $this->assertStringContainsString('3 Nachrichten', $push->message);
    }

    #[TestDox('Einstellung „Benachrichtigung“ im Nachrichten-Eingang: Mail abwählen, Push wählen')]
    public function test_einstellung_im_eingang(): void
    {
        Filament::setCurrentPanel('admin');
        $user = $this->user('verwaltung', ['ntfy_topic' => 'kluft-test-3', 'ntfy_events' => [PushEvents::ANMELDUNGEN]]);
        $this->actingAs($user);
        $push = Features::enabled(Features::PUSH);

        $this->assertTrue($user->wantsMail(MailEvents::SPAMVERDACHT));
        Livewire::test(ListContactMessages::class)
            ->callAction('benachrichtigung', data: ['mail' => false, 'push' => true])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $user->refresh();
        $this->assertFalse($user->wantsMail(MailEvents::SPAMVERDACHT));
        // Ohne Push (Ausbaustufe) bleibt nur die Mail-Wahl.
        $this->assertSame($push, $user->wantsPush(PushEvents::SPAMVERDACHT));
        $this->assertContains(PushEvents::ANMELDUNGEN, $user->ntfy_events);

        // Stufenleitung sieht nur Fragen zu ihren Veranstaltungen, keinen Spamverdacht.
        $this->actingAs($this->user('stufenadmin'));
        Livewire::test(ListContactMessages::class)->assertActionHidden('benachrichtigung');
    }
}
