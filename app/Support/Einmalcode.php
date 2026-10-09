<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Sechsstelliger Einmalcode per E-Mail, z. B. für „Meine Anmeldungen“ und
 * die Anmeldung der Redaktion ohne Microsoft 365. Im Cache liegt nur ein
 * HMAC des Codes; er gilt eine begrenzte Zeit und eine begrenzte Zahl von
 * Versuchen. Ein neuer Code geht frühestens nach einer Sperrzeit raus.
 */
class Einmalcode
{
    public function __construct(
        private string $zweck,
        public readonly int $minuten = 10,
        private int $versuche = 5,
        private int $sperreSekunden = 60,
    ) {}

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** Neuen Code erzeugen; null, solange der letzte noch frisch ist. */
    public function ausstellen(string $email): ?string
    {
        $key = $this->cacheKey($email);
        $current = Cache::get($key);
        if ($current && $current['sent_at'] > now()->subSeconds($this->sperreSekunden)->timestamp) {
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($key, ['hash' => $this->hash($code), 'attempts' => 0, 'sent_at' => now()->timestamp], now()->addMinutes($this->minuten));

        return $code;
    }

    public function pruefen(string $email, string $code): bool
    {
        $key = $this->cacheKey($email);
        $current = Cache::get($key);

        if (! $current || $current['attempts'] >= $this->versuche) {
            return false;
        }

        if (hash_equals($current['hash'], $this->hash((string) preg_replace('/\D/', '', $code)))) {
            Cache::forget($key);

            return true;
        }

        $current['attempts']++;
        Cache::put($key, $current, now()->addMinutes($this->minuten));

        return false;
    }

    private function cacheKey(string $email): string
    {
        return $this->zweck.'-code:'.hash('sha256', self::normalize($email));
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
