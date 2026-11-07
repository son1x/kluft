<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Mail\ContactMessageMail;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\ContactTopic;
use App\Models\Page;
use App\Models\User;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Nachrichten-Eingang: ehrliche Anzeige, wenn Mails nur ins Server-Protokoll
 * gehen (Mailer „log“), dazu „Erneut senden“ und „Weiterleiten“
 * (docs/nachrichten.md).
 */
class NachrichtenVersandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Filament::setCurrentPanel('admin');
        Features::setzen(Features::NACHRICHTEN, true);
        Page::query()->create(['title' => 'Kontakt', 'slug' => 'kontakt', 'body' => '<p>Büro</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        ContactTopic::query()->create(['label' => 'Stufe: Biber', 'slug' => 'biber', 'email' => 'biber@example.org', 'sort' => 10]);
    }

    private function senden(): ContactMessage
    {
        $this->post('/kontakt', [
            'thema' => 'biber', 'name' => 'Erika Muster', 'email' => 'erika@example.org', 'betreff' => 'Schnupperstunde',
            'nachricht' => 'Darf meine Tochter zur Schnupperstunde kommen?', '_t' => Crypt::encryptString((string) now()->subSeconds(20)->timestamp),
        ])->assertSessionHas('kontakt_status', 'gesendet');

        return ContactMessage::query()->latest('id')->firstOrFail();
    }

    private function verwaltung(): User
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $user->fresh();
    }

    private function ansicht(ContactMessage $nachricht): string
    {
        return view('filament.contact-message', ['nachricht' => $nachricht->fresh()])->render();
    }

    #[TestDox('Mailer „log“: Nachricht steht nicht als zugestellt da, sondern „nur ins Server-Protokoll geschrieben, nicht verschickt“')]
    public function test_nur_protokoll_ehrlich_angezeigt(): void
    {
        config(['mail.default' => 'log']);
        $nachricht = $this->senden();

        $this->assertFalse($nachricht->zugestellt);
        $this->assertSame('log', $nachricht->letztesErgebnis());
        $this->assertSame(['eingang', ['biber@example.org'], 'log'], [$nachricht->versand[0]['art'], $nachricht->versand[0]['an'], $nachricht->versand[0]['ergebnis']]);
        $this->assertStringContainsString('nur ins Server-Protokoll geschrieben, nicht verschickt', $this->ansicht($nachricht));
        $this->assertStringNotContainsString('Mail fehlgeschlagen', $this->ansicht($nachricht));

        $this->actingAs($this->verwaltung());
        Livewire::test(ListContactMessages::class)
            ->assertSee('Achtung: Mails werden derzeit nur ins Server-Protokoll geschrieben')
            ->assertDontSee('Die zuständige Person bekommt jede auch per Mail.');
        config(['mail.default' => 'smtp']);
        Livewire::test(ListContactMessages::class)->assertSee('Die zuständige Person bekommt jede auch per Mail.');
    }

    #[TestDox('Mail wird verschickt: zugestellt, Verlauf „verschickt“')]
    public function test_verschickt(): void
    {
        $nachricht = $this->senden();

        $this->assertTrue($nachricht->zugestellt);
        $this->assertSame('mail', $nachricht->letztesErgebnis());
        Mail::assertQueued(ContactMessageMail::class, 1);
    }

    #[TestDox('„Erneut senden“ schickt an die ursprünglichen Empfänger, markiert als zugestellt und steht im Verlauf')]
    public function test_erneut_senden(): void
    {
        config(['mail.default' => 'log']);
        $nachricht = $this->senden();
        config(['mail.default' => 'smtp']);

        $this->actingAs($this->verwaltung());
        Livewire::test(ListContactMessages::class)
            ->callTableAction('erneutSenden', $nachricht)
            ->assertNotified('Erneut gesendet an biber@example.org');

        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('biber@example.org') && $mail->hasReplyTo('erika@example.org'));
        $nachricht->refresh();
        $this->assertTrue($nachricht->zugestellt);
        $this->assertSame(['log', 'mail'], array_column($nachricht->versand, 'ergebnis'));
        $this->assertSame('Büro', $nachricht->versand[1]['von']);
        $this->assertTrue(AuditLog::query()->where('action', 'nachricht.erneut:mail')->exists());
        $this->assertStringContainsString('Erneut gesendet an biber@example.org – verschickt (Büro)', preg_replace('/\s+/', ' ', $this->ansicht($nachricht)));
    }

    #[TestDox('„Weiterleiten“ an eine andere Adresse, mit Hinweis; Antwort geht weiter an die Absender*in')]
    public function test_weiterleiten(): void
    {
        $nachricht = $this->senden();
        $this->actingAs($this->verwaltung());

        Livewire::test(ListContactMessages::class)
            ->callTableAction('weiterleiten', $nachricht, ['an' => 'keine-adresse'])
            ->assertHasTableActionErrors(['an']);
        Mail::assertQueued(ContactMessageMail::class, 1);

        Livewire::test(ListContactMessages::class)
            ->callTableAction('weiterleiten', $nachricht, ['an' => 'woelflinge@example.org', 'hinweis' => 'Ist eher eine Frage für euch.'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Weitergeleitet an woelflinge@example.org');

        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('woelflinge@example.org')
            && $mail->hasReplyTo('erika@example.org')
            && str_contains((string) $mail->context, 'Weitergeleitet von Büro: Ist eher eine Frage für euch.'));
        $this->assertSame(['eingang', 'weitergeleitet'], array_column($nachricht->fresh()->versand, 'art'));
        $this->assertSame(['biber@example.org'], $nachricht->fresh()->empfaenger);
    }

    #[TestDox('Weiterleiten, während Mails nur ins Protokoll gehen: ehrliche Meldung „Nicht verschickt“')]
    public function test_weiterleiten_nur_protokoll(): void
    {
        config(['mail.default' => 'log']);
        $nachricht = $this->senden();
        $this->actingAs($this->verwaltung());

        Livewire::test(ListContactMessages::class)
            ->callTableAction('weiterleiten', $nachricht, ['an' => 'woelflinge@example.org'])
            ->assertNotified('Nicht verschickt');

        $nachricht->refresh();
        $this->assertFalse($nachricht->zugestellt);
        $this->assertSame(['log', 'log'], array_column($nachricht->versand, 'ergebnis'));
    }
}
