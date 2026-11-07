<?php

namespace App\Support;

use App\Models\User;

/**
 * Hinweise, die Personen im Backend per Mail bekommen können (Gegenstück zu
 * PushEvents). Ohne eigene Auswahl (users.mail_events = null) gilt die
 * Voreinstellung: Spamverdacht per Mail an, damit zurückgehaltene
 * Nachrichten nicht unbemerkt verfallen.
 */
class MailEvents
{
    public const SPAMVERDACHT = 'spamverdacht';

    public const LABELS = [
        self::SPAMVERDACHT => 'Nachrichten mit Spamverdacht im Nachrichten-Eingang (täglicher Sammelhinweis)',
    ];

    /** Voreinstellung für Personen ohne eigene Auswahl. */
    public const STANDARD = [self::SPAMVERDACHT];

    /**
     * @return array<string, string>
     */
    public static function availableFor(User $user): array
    {
        $allowed = [
            self::SPAMVERDACHT => self::spamverdachtErlaubt($user),
        ];

        return array_filter(self::LABELS, fn (string $key) => $allowed[$key], ARRAY_FILTER_USE_KEY);
    }

    /** Zurückgehaltene Nachrichten sieht die Verwaltung (alle Nachrichten im Eingang). */
    public static function spamverdachtErlaubt(User $user): bool
    {
        return Features::enabled(Features::NACHRICHTEN) && $user->isVerwaltung();
    }
}
