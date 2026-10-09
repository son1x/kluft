<?php

namespace App\Support;

use App\Mail\ParticipantCodeMail;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
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
        return Einmalcode::normalize($email);
    }

    /**
     * Code erzeugen und schicken – nur wenn es Anmeldungen zur Adresse gibt
     * und nicht gerade erst einer verschickt wurde.
     */
    public function sendCode(string $email): void
    {
        $email = self::normalize($email);
        if (! EventRegistration::query()->where('email', $email)->exists()) {
            return;
        }

        $code = $this->codes()->ausstellen($email);
        if ($code === null) {
            return;
        }

        try {
            Mail::to($email)->send(new ParticipantCodeMail($code));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function verify(string $email, string $code): bool
    {
        return $this->codes()->pruefen(self::normalize($email), $code);
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

    private function codes(): Einmalcode
    {
        return new Einmalcode('teilnehmende', self::CODE_MINUTES, self::MAX_ATTEMPTS, self::RESEND_SECONDS);
    }
}
