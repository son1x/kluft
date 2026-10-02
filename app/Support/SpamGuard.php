<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Spamschutz für öffentliche Formulare ohne Captcha und ohne Drittanbieter:
 * Honeypot-Feld „website“ plus Zeitfalle (verschlüsselter Zeitstempel beim
 * Anzeigen; wer schneller als in drei Sekunden absendet, ist ein Bot).
 * Dazu kommt das Rate-Limit an der Route.
 */
class SpamGuard
{
    public const MIN_SECONDS = 3;

    public const MAX_SECONDS = 86400;

    /** Wert für das versteckte Feld „_t“. */
    public static function token(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    /**
     * 'bot' = stillschweigend verwerfen, 'abgelaufen' = Formular war zu
     * lange offen (Seite neu laden lassen), null = in Ordnung.
     */
    public static function check(Request $request): ?string
    {
        if (filled($request->input('website'))) {
            return 'bot';
        }

        try {
            $shownAt = (int) Crypt::decryptString((string) $request->input('_t'));
        } catch (DecryptException) {
            return 'bot';
        }

        $age = now()->timestamp - $shownAt;

        return match (true) {
            $age < self::MIN_SECONDS => 'bot',
            $age > self::MAX_SECONDS => 'abgelaufen',
            default => null,
        };
    }
}
