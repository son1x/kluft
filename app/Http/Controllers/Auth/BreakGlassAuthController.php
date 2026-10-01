<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\BreakGlassLoginNotification;
use App\Support\AuditLogger;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

/**
 * Notfall-Login für den Super-Admin (Break-Glass), s. Pflichtenheft 5.2.
 * Bewusst NICHT Teil des Filament-Panels/der Entra-Anmeldung – eigener,
 * nicht verlinkter Pfad, Rate-Limiting, TOTP-Pflicht, Alarmmail bei
 * jeder erfolgreichen Anmeldung.
 */
class BreakGlassAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_MINUTES = 15;

    public function show(): View
    {
        return view('auth.notfall-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'totp' => ['required', 'string'],
        ]);

        $throttleKey = 'notfall-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Zu viele Versuche. Bitte in {$seconds} Sekunden erneut versuchen.",
            ]);
        }

        $user = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($data['email']))])
            ->where('is_local_admin', true)
            ->first();

        $passwordValid = $user && Hash::check($data['password'], $user->password);

        try {
            $totpValid = $passwordValid && (new Google2FA)->verifyKey($user->totp_secret, $data['totp']);
        } catch (DecryptException) {
            // Das TOTP-Geheimnis wurde mit einem anderen APP_KEY verschlüsselt
            // (z. B. Konto während eines Deploys angelegt). Ohne neues Secret
            // ist dieser Account nicht mehr nutzbar – deutlich melden statt 500.
            Log::critical('Notfall-Login: TOTP-Geheimnis nicht entschlüsselbar (APP_KEY geändert?). Account per "php artisan cms:create-superadmin" neu einrichten.', ['user_id' => $user->id]);
            RateLimiter::hit($throttleKey, self::DECAY_MINUTES * 60);

            throw ValidationException::withMessages([
                'email' => 'Der Notfall-Account muss neu eingerichtet werden (TOTP-Geheimnis nicht lesbar). Bitte per SSH „php artisan cms:create-superadmin“ ausführen.',
            ]);
        }

        if (! $user || ! $passwordValid || ! $totpValid || ! $user->isActive()) {
            RateLimiter::hit($throttleKey, self::DECAY_MINUTES * 60);

            AuditLogger::log('login.breakglass.failed', $user, request: $request);

            throw ValidationException::withMessages([
                'email' => 'Zugangsdaten oder TOTP-Code ungültig.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLogger::log('login.breakglass', $user, request: $request);

        // Jede erfolgreiche Notfall-Anmeldung löst eine Alarmmail an alle
        // Admins aus (s. 5.2) – bewusst synchron, nicht über die Queue,
        // damit ein Queue-Ausfall die Benachrichtigung nicht unterdrückt.
        Notification::send(
            User::query()->whereHas(
                'roleAssignments',
                fn ($query) => $query->where('role', 'admin')
            )->get(),
            new BreakGlassLoginNotification($user, AuditLogger::truncateIp($request->ip()) ?? 'unbekannt')
        );

        return redirect()->intended('/admin');
    }
}
