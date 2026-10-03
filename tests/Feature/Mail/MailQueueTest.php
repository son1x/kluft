<?php

namespace Tests\Feature\Mail;

use App\Filament\Pages\MailQueue;
use App\Mail\ContactMessageMail;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\TestCase;

/**
 * Mailversand über die Warteschlange (F-MAIL-05): asynchron, verschlüsselt,
 * mit Wiederholungen, Tageslimit und Fehleranzeige im Backend.
 */
class MailQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database', 'mail.default' => 'array', 'mail.test_redirect_to' => null]);
    }

    private function queueMail(string $to = 'buero@dpsg-trier.de', string $text = 'Geheime Frage zum Lager'): void
    {
        Mail::to($to)->send(new ContactMessageMail('Allgemein', 'Erika Muster', 'erika@example.org', 'Betreff', $text));
    }

    /** @return list<string> */
    private function sentTo(): array
    {
        return collect(app('mailer')->getSymfonyTransport()->messages())
            ->map(fn ($m) => $m->getOriginalMessage()->getTo()[0]->getAddress())->values()->all();
    }

    private function verwaltung(): User
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $user->fresh();
    }

    #[TestDox('Mails landen verschlüsselt in der Warteschlange; der Worker aus dem Cronjob verschickt sie')]
    public function test_mail_is_queued_encrypted_and_sent_by_worker(): void
    {
        $this->queueMail();

        $this->assertSame(1, DB::table('jobs')->count());
        $payload = (string) DB::table('jobs')->value('payload');
        $this->assertStringNotContainsString('Geheime Frage', $payload);
        $this->assertStringNotContainsString('erika@example.org', $payload);
        $this->assertSame([], $this->sentTo());

        $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(['buero@dpsg-trier.de'], $this->sentTo());
        $this->assertSame(0, DB::table('jobs')->count());

        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");
        $this->assertStringContainsString('queue:work --stop-when-empty', $commands);
        $this->assertStringContainsString('queue:prune-failed', $commands);
    }

    #[TestDox('Ist das Tageslimit erreicht, wartet die Mail statt zu scheitern')]
    public function test_daily_limit_holds_back_mails(): void
    {
        config(['mail.daily_limit' => 1]);
        $this->queueMail('a@dpsg-trier.de');
        $this->queueMail('b@dpsg-trier.de');

        $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(['a@dpsg-trier.de'], $this->sentTo());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    #[TestDox('Fehlgeschlagene Mails stehen mit Art, Empfänger*in und Fehler im Backend; erneut versuchen oder löschen')]
    public function test_failed_mails_in_backend(): void
    {
        $this->queueMail('referent@dpsg-trier.de');
        $job = DB::table('jobs')->first();
        DB::table('failed_jobs')->insert([
            'uuid' => 'f-1', 'connection' => 'database', 'queue' => 'default', 'payload' => $job->payload,
            'exception' => (new RuntimeException('Expected response code "250" but got "421".'))."\n#0 trace", 'failed_at' => now(),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'f-2', 'connection' => 'database', 'queue' => 'default', 'payload' => $job->payload,
            'exception' => 'Fehler', 'failed_at' => now(),
        ]);
        DB::table('jobs')->delete();

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());
        $this->assertSame('2', MailQueue::getNavigationBadge());

        Livewire::test(MailQueue::class)
            ->assertSee('Kontaktformular')
            ->assertSee('referent@dpsg-trier.de')
            ->assertSee('Expected response code')
            ->assertDontSee('#0 trace')
            ->call('retry', 'f-1')
            ->call('forget', 'f-2');

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->count());
    }

    #[TestDox('Nur Verwaltung und Admins sehen den E-Mail-Versand')]
    public function test_access(): void
    {
        $autor = User::query()->create(['name' => 'Autor', 'email' => 'autor@dpsg-trier.de', 'status' => 'active']);
        $autor->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own']);

        $this->actingAs($autor)->get(MailQueue::getUrl(panel: 'admin'))->assertForbidden();
        $this->actingAs($this->verwaltung())->get(MailQueue::getUrl(panel: 'admin'))->assertOk()->assertSee('Warteschlange');
    }
}
