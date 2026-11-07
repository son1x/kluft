<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use App\Support\Spamverdacht;
use Illuminate\Console\Command;

/**
 * Löschfrist des Nachrichten-Eingangs (docs/nachrichten.md): Nachrichten
 * werden nach dpsg.nachrichten.aufbewahrung_monate gelöscht – auch wenn die
 * Funktion inzwischen ausgeschaltet ist.
 */
class ContactMessagePruneCommand extends Command
{
    protected $signature = 'kontakt:prune';

    protected $description = 'Löscht Nachrichten im Nachrichten-Eingang nach Ablauf der Aufbewahrungsfrist';

    public function handle(): int
    {
        $n = ContactMessage::query()->where('created_at', '<', now()->subMonths(ContactMessage::aufbewahrungMonate()))->delete();
        // Spamverdacht, der nicht zugestellt wurde, schon nach 30 Tagen.
        $n += ContactMessage::query()->where('status', 'spam')->where('created_at', '<', now()->subDays(Spamverdacht::TAGE))->delete();
        $this->info("Gelöscht: {$n} Nachrichten.");

        return self::SUCCESS;
    }
}
