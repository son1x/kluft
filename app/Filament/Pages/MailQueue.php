<?php

namespace App\Filament\Pages;

use App\Support\AuditLogger;
use App\Support\MailQueueEntry;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * E-Mail-Versand (F-MAIL-05): wartende und fehlgeschlagene Mails der
 * Warteschlange; fehlgeschlagene lassen sich erneut versuchen oder löschen.
 */
class MailQueue extends Page
{
    protected string $view = 'filament.pages.mail-queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'E-Mail-Versand';

    protected static ?string $title = 'E-Mail-Versand';

    protected static ?string $slug = 'e-mail-versand';

    protected static ?int $navigationSort = 92;

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
        ];
    }

    public function retry(string $uuid): void
    {
        abort_unless(static::canAccess(), 403);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        AuditLogger::log('mail.retry', Filament::auth()->user(), request: request());
        Notification::make()->title('Mail wird erneut versucht (innerhalb einer Minute).')->success()->send();
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
