<?php

namespace Tests\Feature\Mail;

use App\Filament\Pages\MailQueue;
use App\Models\User;
use App\Support\Health\HealthChecks;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Welcher Mailer beim Deploy in die .env kommt (scripts/mailer-waehlen.sh,
 * docs/deploy.md „Mail“), und dass „nur ins Log“ im Backend und im Status
 * auffällt. Anlass: Bei Roden (live) gingen Kontaktnachrichten nur ins Log,
 * weil echte Mails an die Test-Umleitung gekoppelt waren.
 */
class MailerWahlTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: array<string, string>, 1: string, 2: bool}> */
    public static function faelle(): array
    {
        $brevo = ['BREVO_SMTP_LOGIN' => 'login', 'BREVO_SMTP_KEY' => 'schluessel'];

        return [
            'ohne Brevo' => [[], 'log', true],
            'ohne Brevo, aber live' => [['KLUFT_MAIL_LIVE' => 'ja'], 'log', true],
            'Testumleitung' => [[...$brevo, 'MAIL_TEST_REDIRECT_TO' => 'test@example.org'], 'smtp', false],
            'Testumleitung auf Testsystem' => [[...$brevo, 'MAIL_TEST_REDIRECT_TO' => 'test@example.org', 'KLUFT_TESTSYSTEM' => 'offen'], 'smtp', false],
            'Testsystem ohne Umleitung' => [[...$brevo, 'KLUFT_TESTSYSTEM' => 'geschlossen'], 'log', true],
            'Testsystem ohne Umleitung, trotz live' => [[...$brevo, 'KLUFT_TESTSYSTEM' => 'offen', 'KLUFT_MAIL_LIVE' => 'ja'], 'log', true],
            'live (Roden, DV nach Livegang)' => [[...$brevo, 'KLUFT_MAIL_LIVE' => 'ja'], 'smtp', false],
            'live, andere Schreibweise' => [[...$brevo, 'KLUFT_MAIL_LIVE' => ' True '], 'smtp', false],
            'weder Umleitung noch live' => [$brevo, 'log', true],
            'live = nein' => [[...$brevo, 'KLUFT_MAIL_LIVE' => 'nein'], 'log', true],
            'nur Leerzeichen' => [['BREVO_SMTP_LOGIN' => ' ', 'BREVO_SMTP_KEY' => ' ', 'MAIL_TEST_REDIRECT_TO' => ' '], 'log', true],
        ];
    }

    /** @param  array<string, string>  $umgebung */
    #[DataProvider('faelle')]
    #[TestDox('Deploy wählt den Mailer: $_dataName')]
    public function test_mailer_wahl(array $umgebung, string $mailer, bool $hinweis): void
    {
        $ergebnis = Process::path(base_path())
            ->env(['BREVO_SMTP_LOGIN' => '', 'BREVO_SMTP_KEY' => '', 'MAIL_TEST_REDIRECT_TO' => '', 'KLUFT_TESTSYSTEM' => '', 'KLUFT_MAIL_LIVE' => '', ...$umgebung])
            ->run(['bash', 'scripts/mailer-waehlen.sh']);

        $this->assertTrue($ergebnis->successful(), $ergebnis->errorOutput());
        $zeilen = explode("\n", trim($ergebnis->output()));
        $this->assertSame($mailer, $zeilen[0]);
        $this->assertSame($hinweis, isset($zeilen[1]) && str_starts_with($zeilen[1], 'Mails gehen nur ins Log'));
    }

    #[TestDox('Status und Monitoring warnen, wenn Mails nur ins Log gehen')]
    public function test_status_warnt_bei_log(): void
    {
        config(['mail.default' => 'log']);
        $mails = collect(app(HealthChecks::class)->run())->firstWhere('key', 'mails');
        $this->assertSame([HealthChecks::WARN, 'Mails werden nur protokolliert, nicht verschickt (Mailer „log“).'], [$mails['status'], $mails['message']]);

        config(['mail.default' => 'smtp']);
        $this->assertSame(HealthChecks::OK, collect(app(HealthChecks::class)->run())->firstWhere('key', 'mails')['status']);
    }

    #[TestDox('„E-Mail-Versand“ im Backend zeigt deutlich, wenn Mails nur ins Log gehen')]
    public function test_backend_hinweis(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@dpsg-trier.de', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $this->actingAs($admin->fresh());

        config(['mail.default' => 'log']);
        Livewire::test(MailQueue::class)->assertSee('Mails werden nicht verschickt')->assertSee('KLUFT_MAIL_LIVE=ja');

        config(['mail.default' => 'smtp']);
        Livewire::test(MailQueue::class)->assertDontSee('Mails werden nicht verschickt');
    }
}
