<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Spamschutz für öffentliche Formulare ohne Captcha und ohne Drittanbieter:
 * Honeypot-Feld „website“ plus Zeitfalle (verschlüsselter Zeitstempel beim
 * Anzeigen; wer schneller als in fünf Sekunden absendet, ist ein Bot). Jeder
 * Zeitstempel gilt zwei Stunden und nur für eine Absendung: Wer das Formular
 * einmal lädt und den Wert immer wieder schickt (Spam-Programme, Botnetze mit
 * wechselnden IP-Adressen), wird verworfen. Nach einem Fehler zeigt das
 * Formular ohnehin einen frischen Zeitstempel. Dazu kommt das Rate-Limit an
 * der Route.
 */
class SpamGuard
{
    public const MIN_SECONDS = 5;

    public const MAX_SECONDS = 7200;

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
            ! self::verbrauchen((string) $request->input('_t')) => 'bot',
            default => null,
        };
    }

    /**
     * Zeitstempel als benutzt merken; false, wenn er schon einmal benutzt
     * wurde. Cache::add ist atomar, auch zwei gleichzeitige Absendungen mit
     * demselben Wert kommen nicht beide durch. Ist der Cache gestört, lieber
     * durchlassen als echte Nachrichten verlieren (die übrigen Prüfungen und
     * das Rate-Limit greifen weiter).
     */
    private static function verbrauchen(string $token): bool
    {
        try {
            return Cache::add('spamguard:'.hash('sha256', $token), true, self::MAX_SECONDS + 60);
        } catch (Throwable) {
            return true;
        }
    }
}
