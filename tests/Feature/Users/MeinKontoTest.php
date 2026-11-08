<?php

namespace Tests\Feature\Users;

use App\Filament\Pages\MeineBenachrichtigungen;
use App\Filament\Pages\MeineRollen;
use App\Models\User;
use App\Support\Features;
use App\Support\MailEvents;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Mein Konto: „Meine Rollen und Rechte“ (nur lesen) und „Meine
 * Benachrichtigungen“ (Mail-Hinweise wählen, Stand der Push-Nachrichten).
 */
class MeinKontoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function user(string $email, array $rollen, array $attrs = []): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active', ...$attrs]);
        foreach ($rollen as [$role, $scopeType, $scopeKey]) {
            $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);
        }

        return $user->fresh();
    }

    #[TestDox('Meine Rollen und Rechte: eigene Rollen mit Erklärung, Datum und wer sie vergeben hat; für alle aktiven Konten')]
    public function test_meine_rollen(): void
    {
        $buero = $this->user('buero@dpsg-trier.de', [['verwaltung', 'global', null]]);
        $stage = array_key_first((array) config('dpsg.stages'));
        $label = config("dpsg.stages.{$stage}.label");
        $leitung = $this->user('leitung@dpsg-trier.de', [['stufenadmin', 'stage', $stage], ['autor', 'own', null]]);
        $leitung->roleAssignments()->update(['granted_by' => $buero->id]);

        $this->actingAs($leitung);
        $this->get(MeineRollen::getUrl())->assertOk()
            ->assertSee('Stufenadmin – '.$label)
            ->assertSee('Stufenseite, Beiträge und Veranstaltungen der Stufe pflegen')
            ->assertSee('Autor:in (ohne Zuordnung)')
            ->assertSee('vergeben von Buero')
            ->assertSee('leitung@dpsg-trier.de')
            // Keine fremden Rollen.
            ->assertDontSee('Verwaltung (alle Inhalte');

        $inaktiv = $this->user('weg@dpsg-trier.de', [['autor', 'own', null]], ['status' => 'disabled']);
        $this->actingAs($inaktiv);
        $this->assertFalse(MeineRollen::canAccess());
    }

    #[TestDox('Meine Benachrichtigungen: Verwaltung wählt den Spamverdacht-Hinweis per Mail ab; andere Einträge bleiben')]
    public function test_mail_hinweise(): void
    {
        Features::setzen(Features::NACHRICHTEN, true);
        $buero = $this->user('buero@dpsg-trier.de', [['verwaltung', 'global', null]], ['mail_events' => null]);
        $this->actingAs($buero);

        $this->assertTrue($buero->wantsMail(MailEvents::SPAMVERDACHT));
        Livewire::test(MeineBenachrichtigungen::class)
            ->assertSee('Per Mail an buero@dpsg-trier.de')
            ->assertSet('data.mail', [MailEvents::SPAMVERDACHT])
            ->set('data.mail', [])
            ->call('speichern')
            ->assertNotified('Gespeichert');
        $this->assertFalse($buero->fresh()->wantsMail(MailEvents::SPAMVERDACHT));

        // Ein Eintrag, den diese Seite nicht anbietet, bleibt erhalten.
        $buero->forceFill(['mail_events' => ['kuenftig']])->save();
        Livewire::test(MeineBenachrichtigungen::class)->set('data.mail', [MailEvents::SPAMVERDACHT])->call('speichern');
        $this->assertSame(['kuenftig', MailEvents::SPAMVERDACHT], $buero->fresh()->mail_events);
    }

    #[TestDox('Meine Benachrichtigungen: ohne wählbare Hinweise nur sichtbar, wenn es Push gibt; Push-Stand mit Weg zur Einrichtung')]
    public function test_sichtbarkeit_und_push(): void
    {
        $autor = $this->user('autor@dpsg-trier.de', [['autor', 'own', null]]);
        $this->actingAs($autor);

        Features::setzen(Features::PUSH, false);
        $this->assertFalse(MeineBenachrichtigungen::canAccess());
        $this->get(MeineBenachrichtigungen::getUrl())->assertForbidden();

        Features::setzen(Features::PUSH, true);
        $this->get(MeineBenachrichtigungen::getUrl())->assertOk()
            ->assertSee('keine Hinweise per Mail zum Auswählen')
            ->assertSee('Push-Nachrichten sind noch nicht eingeschaltet')
            ->assertSee('Push-Nachrichten einrichten');

        $autor->forceFill(['ntfy_topic' => 'dpsg-test-abc', 'ntfy_events' => ['rechte']])->save();
        $this->get(MeineBenachrichtigungen::getUrl())->assertOk()
            ->assertSee('Push-Nachrichten sind eingeschaltet')
            ->assertSee('Push-Nachrichten ändern');
    }
}
