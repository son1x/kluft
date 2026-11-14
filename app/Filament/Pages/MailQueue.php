<?php

namespace App\Filament\Pages;

use App\Mail\TestRecipientFilter;
use App\Support\AuditLogger;
use App\Support\MailQueueEntry;
use App\Support\Mailversand;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * E-Mail-Versand (F-MAIL-05): wartende und fehlgeschlagene Mails der
 * Warteschlange; fehlgeschlagene lassen sich erneut versuchen oder löschen.
 */
class MailQueue extends Page
{
    protected string $view = 'filament.pages.mail-queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'E-Mail-Versand';

    protected static ?string $title = 'E-Mail-Versand';

    protected static ?string $slug = 'e-mail-versand';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isVerwaltung();
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = DB::table('failed_jobs')->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'pending' => DB::table('jobs')->count(),
            'oldestPending' => DB::table('jobs')->min('created_at'),
            'failed' => DB::table('failed_jobs')->orderByDesc('failed_at')->limit(100)->get()
                ->map(fn (object $row) => (object) [
                    'uuid' => $row->uuid,
                    'failed_at' => $row->failed_at,
                    ...MailQueueEntry::describe($row->payload),
                    'error' => MailQueueEntry::shortError($row->exception),
                ]),
            'dailyLimit' => (int) config('mail.daily_limit'),
            'nurLog' => Mailversand::nurProtokoll(),
            'testUmleitung' => filled(config('mail.test_redirect_to')),
            'testSecret' => TestRecipientFilter::ausSecret(),
            'testBackend' => TestRecipientFilter::ausBackend(),
            'testWirksam' => TestRecipientFilter::allowlist(),
            'istAdmin' => (bool) Filament::auth()->user()?->isAdmin(),
        ];
    }

    /** Neue Test-Empfänger*in (Adresse oder @domain) für die Testumgebung. */
    public string $neueTestAdresse = '';

    public function testEmpfaengerHinzufuegen(): void
    {
        abort_unless((bool) Filament::auth()->user()?->isAdmin(), 403);
        $this->resetErrorBag('neueTestAdresse');
        $eintrag = TestRecipientFilter::normalisieren($this->neueTestAdresse);
        if (! TestRecipientFilter::istGueltig($eintrag)) {
            $this->addError('neueTestAdresse', 'Bitte eine E-Mail-Adresse oder „@domain.de“ eingeben.');

            return;
        }
        if (in_array($eintrag, TestRecipientFilter::allowlist(), true)) {
            $this->addError('neueTestAdresse', 'Diese Adresse bekommt ihre Mails schon direkt.');

            return;
        }
        if (count(TestRecipientFilter::allowlist()) >= TestRecipientFilter::MAX_ALLOWED) {
            $this->addError('neueTestAdresse', 'Es sind schon '.TestRecipientFilter::MAX_ALLOWED.' Einträge freigeschaltet (Grenze).');

            return;
        }
        TestRecipientFilter::backendSetzen([...TestRecipientFilter::ausBackend(), $eintrag], Filament::auth()->id());
        AuditLogger::log('mail.allowlist.hinzu', Filament::auth()->user(), request: request());
        $this->neueTestAdresse = '';
        Notification::make()->title('Freigeschaltet: Mails an diese Adresse gehen ab sofort direkt raus.')->success()->send();
    }

    public function testEmpfaengerEntfernen(string $eintrag): void
    {
        abort_unless((bool) Filament::auth()->user()?->isAdmin(), 403);
        TestRecipientFilter::backendSetzen(array_values(array_diff(TestRecipientFilter::ausBackend(), [$eintrag])), Filament::auth()->id());
        AuditLogger::log('mail.allowlist.entfernt', Filament::auth()->user(), request: request());
        Notification::make()->title('Entfernt.')->success()->send();
    }

    public function retry(string $uuid): void
    {
        abort_unless(static::canAccess(), 403);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        AuditLogger::log('mail.retry', Filament::auth()->user(), request: request());
        Notification::make()->title('Mail wird erneut versucht (innerhalb einer Minute).')->success()->send();
    }

    /**
     * Wartende Mails sofort abarbeiten (statt auf den Cronjob zu warten): ein Worker im Web-Aufruf, höchstens
     * etwa eine halbe Minute. Gleiche Regeln wie der Cronjob (Wiederholungen, Tageslimit, Testumleitung).
     */
    public function jetztSenden(): void
    {
        abort_unless(static::canAccess(), 403);
        $vorher = DB::table('jobs')->count();
        @set_time_limit(60);
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 25, '--sleep' => 1, '--tries' => 5]);
        $nachher = DB::table('jobs')->count();
        AuditLogger::log('mail.jetzt_senden:'.max(0, $vorher - $nachher), Filament::auth()->user(), request: request());

        $versendet = max(0, $vorher - $nachher);
        $titel = $vorher === 0
            ? 'Es warten keine Mails.'
            : ($nachher === 0 ? "Alle {$vorher} wartenden Mails sind abgearbeitet." : "{$versendet} von {$vorher} Mails abgearbeitet, {$nachher} warten noch (Knopf nochmal drücken).");
        Notification::make()->title($titel)->{$nachher === 0 ? 'success' : 'warning'}()->send();
    }

    public function retryAll(): void
    {
        abort_unless(static::canAccess(), 403);
        Artisan::call('queue:retry', ['id' => ['all']]);
        AuditLogger::log('mail.retry_all', Filament::auth()->user(), request: request());
        Notification::make()->title('Alle fehlgeschlagenen Mails werden erneut versucht.')->success()->send();
    }

    public function forget(string $uuid): void
    {
        abort_unless(static::canAccess(), 403);
        Artisan::call('queue:forget', ['id' => $uuid]);
        AuditLogger::log('mail.forget', Filament::auth()->user(), request: request());
        Notification::make()->title('Mail gelöscht.')->success()->send();
    }
}
