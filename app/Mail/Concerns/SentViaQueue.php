<?php

namespace App\Mail\Concerns;

use DateTimeInterface;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Versand über die Warteschlange (F-MAIL-05): bei Fehlern bis zu fünf
 * Versuche mit wachsendem Abstand; ist das Tageslimit des Brevo-Tarifs
 * erreicht (RateLimiter „brevo-tageslimit“), wartet die Mail – höchstens
 * zwei Tage. Der Inhalt liegt verschlüsselt in der Datenbank
 * (ShouldBeEncrypted an der Klasse). Ist die Anmeldung inzwischen
 * gelöscht, entfällt die Mail.
 */
trait SentViaQueue
{
    public int $maxExceptions = 5;

    public bool $deleteWhenMissingModels = true;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDays(2);
    }

    /**
     * Das Brevo-Tageslimit gilt nur für Mails, nicht für Push (ntfy).
     *
     * @return list<object>
     */
    public function middleware(?object $notifiable = null, ?string $channel = null): array
    {
        return $channel !== null && $channel !== 'mail' ? [] : [new RateLimited('brevo-tageslimit')];
    }
}
