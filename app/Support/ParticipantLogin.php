<?php

namespace App\Support;

use App\Mail\ParticipantCodeMail;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * „Meine Anmeldungen“ ohne Konto: Wer eine E-Mail-Adresse angibt, unter der
 * es Anmeldungen gibt, bekommt einen sechsstelligen Einmalcode. Der Code
 * liegt nur als HMAC im Cache, gilt zehn Minuten und fünf Versuche lang;
 * danach ist die Adresse für eine Stunde in der Sitzung freigeschaltet.
 * Ob es Anmeldungen gibt, verrät die Antwort nicht.
 */
class ParticipantLogin
{
    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const SESSION_MINUTES = 60;

    private const SESSION_KEY = 'teilnehmende';

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Code erzeugen und schicken – nur wenn es Anmeldungen zur Adresse gibt
     * und nicht gerade erst einer verschickt wurde.
     */
    public function sendCode(string $email): void
    {
        $email = self::normalize($email);
        $key = $this->cacheKey($email);

        $current = Cache::get($key);
        if ($current && $current['sent_at'] > now()->subSeconds(self::RESEND_SECONDS)->timestamp) {
            return;
        }

        if (! EventRegistration::query()->where('email', $email)->exists()) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($key, ['hash' => $this->hash($code), 'attempts' => 0, 'sent_at' => now()->timestamp], now()->addMinutes(self::CODE_MINUTES));

        try {
            Mail::to($email)->send(new ParticipantCodeMail($code));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function verify(string $email, string $code): bool
    {
        $email = self::normalize($email);
        $key = $this->cacheKey($email);
        $current = Cache::get($key);

        if (! $current || $current['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (hash_equals($current['hash'], $this->hash(preg_replace('/\D/', '', $code)))) {
            Cache::forget($key);

            return true;
        }

        $current['attempts']++;
        Cache::put($key, $current, now()->addMinutes(self::CODE_MINUTES));

        return false;
    }

    public function login(Request $request, string $email): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, ['email' => self::normalize($email), 'until' => now()->addMinutes(self::SESSION_MINUTES)->timestamp]);
    }

    public function logout(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();
    }

    /**
     * Freigeschaltete Adresse dieser Sitzung oder null.
     */
    public function email(Request $request): ?string
    {
        $login = $request->session()->get(self::SESSION_KEY);

        return is_array($login) && ($login['until'] ?? 0) > now()->timestamp ? $login['email'] : null;
    }

    private function cacheKey(string $email): string
    {
        return 'teilnehmende-code:'.hash('sha256', $email);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
