<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\SpamverdachtNotification;
use App\Support\Features;
use App\Support\MailEvents;
use App\Support\PushEvents;
use App\Support\Spamverdacht;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Täglicher Sammelhinweis an die Verwaltung, wenn seit dem letzten Lauf
 * Nachrichten mit Spamverdacht zurückgehalten wurden (docs/nachrichten.md).
 * Ohne neue Fälle passiert nichts.
 */
class SpamverdachtMeldenCommand extends Command
{
    public const CACHE_KEY = 'nachrichten.spamverdacht.gemeldet_bis';

    protected $signature = 'nachrichten:spamverdacht-melden';

    protected $description = 'Meldet neue Nachrichten mit Spamverdacht per Mail bzw. Push an die Verwaltung';

    public function handle(): int
    {
        if (! Features::enabled(Features::NACHRICHTEN)) {
            return self::SUCCESS;
        }

        $bis = now();
        // Ohne gemerkten Stand (erster Lauf, Cache geleert): die letzten 24 Stunden.
        $seit = Cache::get(self::CACHE_KEY) ?? $bis->copy()->subDay()->timestamp;
        $neu = ContactMessage::query()->where('status', 'spam')
            ->where('created_at', '>', date('Y-m-d H:i:s', (int) $seit))
            ->where('created_at', '<=', $bis)
            ->count();
        Cache::forever(self::CACHE_KEY, $bis->timestamp);

        if ($neu === 0) {
            $this->info('Keine neuen Nachrichten mit Spamverdacht.');

            return self::SUCCESS;
        }

        $alle = ContactMessage::query()->where('status', 'spam');
        $aelteste = (clone $alle)->min('created_at');
        $empfaenger = User::query()->where('status', 'active')->with('roleAssignments')->get()
            ->filter(fn (User $user) => $user->wantsMail(MailEvents::SPAMVERDACHT) || $user->wantsPush(PushEvents::SPAMVERDACHT));

        Notification::send($empfaenger, new SpamverdachtNotification(
            $neu,
            (clone $alle)->count(),
            $aelteste ? now()->parse($aelteste)->addDays(Spamverdacht::TAGE) : null,
        ));

        $this->info("{$neu} neue Nachricht(en) mit Spamverdacht, gemeldet an {$empfaenger->count()} Person(en).");

        return self::SUCCESS;
    }
}
